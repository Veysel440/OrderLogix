<?php

namespace App\Console\Commands;


use App\Models\{ProcessedMessage, Product, Reservation};
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use App\Services\Rabbit\RetryHelper as RH;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeInventory extends Command
{
    protected $signature = 'mq:consume:inventory';
    protected $description = 'Consumes inventory.reserve and reserves stock with retries';

    public function handle(): int
    {
        $q = 'inventory.reserve.q';
        $max = (int) env('INVENTORY_MAX_RETRY',3);

        $conn = ConnectionFactory::connect();
        $ch = $conn->channel();
        $ch->basic_qos(null, 16, null);
        $pub = new Publisher($ch);

        $this->info("inventory listening on {$q}");
        $io = $this;

        $handleFail = function(AMQPMessage $m, \Throwable $e) use ($ch,$pub,$max,$io) {
            $n = RH::retryCount($m);
            $next = RH::nextQueue($n);
            if ($next && $n < $max) {
                $props = array_merge($m->get_properties(), RH::withRetryHeaders($m, $n+1));
                $retryMsg = new AMQPMessage($m->getBody(), $props);
                $ch->basic_publish($retryMsg, '', $next);
                $io->line("retry[$n] -> {$next}");
            } else {
                $pub->publish('inventory.dlx','inventory.reserve.dlq',[
                    'type'=>'inventory.reserve.failed',
                    'message_id'=>(string)\Illuminate\Support\Str::uuid(),
                    'occurred_at'=>now()->toISOString(),
                    'error'=> substr($e->getMessage(),0,200),
                    'payload'=> json_decode($m->getBody(),true)
                ]);
                $io->line('→ sent to DLQ');
            }
            $m->ack();
        };

        $cb = function(AMQPMessage $m) use ($pub,$handleFail,$io) {
            try {
                $payload = json_decode($m->getBody(), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                $data = $payload['data'] ?? null;
                if (!$data || empty($data['items'])) { $io->line('skip: bad payload'); $m->ack(); return; }

                $mid = $payload['message_id'] ?? null;
                if ($mid) {
                    try {
                        ProcessedMessage::create(['message_id'=>$mid,'consumer'=>'inventory','processed_at'=>now()]);
                    } catch (\Throwable) { $io->line("dup: $mid"); $m->ack(); return; }
                }

                DB::transaction(function() use ($data) {
                    foreach ($data['items'] as $i) {
                        $sku = $i['sku']; $qty = (int)$i['qty'];
                        $p = Product::where('sku',$sku)->lockForUpdate()->firstOrFail();
                        if ($p->stock_qty - $p->reserved_qty < $qty) {
                            throw new \RuntimeException("insufficient stock for $sku");
                        }
                        $p->reserved_qty += $qty; $p->save();
                        Reservation::updateOrCreate(
                            ['order_id'=>$data['order_id'] ?? null,'product_id'=>$p->id],
                            ['qty'=>$qty,'status'=>'RESERVED']
                        );
                    }
                });

                $io->line("reserved: order_id=".($data['order_id'] ?? 'null')." items=".count($data['items']));

                $pub->publish('inventory.x','inventory.reserved',[
                    'message_id'=>(string)\Illuminate\Support\Str::uuid(),
                    'type'=>'inventory.reserved',
                    'occurred_at'=>now()->toISOString(),
                    'data'=>$data,
                ],['x-causation-id'=>$mid]);

                $io->line('→ published inventory.reserved');
                $m->ack();
            } catch (\Throwable $e) {
                logger()->warning('inventory.reserve failed', ['err'=>$e->getMessage()]);
                $handleFail($m,$e);
            }
        };

        $ch->basic_consume($q,'',false,false,false,false,$cb);
        while ($ch->is_consuming()) { $ch->wait(); }
        $ch->close(); $conn->close();
        return self::SUCCESS;
    }
}
