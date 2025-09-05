<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Reservation;
use App\Support\Telemetry;
use App\Support\Pulse;
use App\Support\EventSchema;
use App\Services\Rabbit\RetryHelper as RH;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

final class MqConsumeInventory extends Command
{
    protected $signature = 'mq:consume:inventory';
    protected $description = 'Consumes inventory.reserve, reserves stock, emits inventory.reserved (retry/DLQ).';

    public function handle(): int
    {
        $q = env('INVENTORY_QUEUE', 'inventory.reserve.q');
        $max = (int) env('INVENTORY_MAX_RETRY', 3);
        $prefetch = (int) env('INVENTORY_PREFETCH', 16);

        $c  = ConnectionFactory::connect('inventory');
        $ch = $c->channel();
        $ch->basic_qos(null, $prefetch, null);
        $pub = new Publisher($ch);

        $this->info("inventory ← {$q}");

        $onFail = function (AMQPMessage $m, \Throwable $e) use ($ch, $pub, $max) {
            $n = RH::retryCount($m);
            Pulse::send('inventory','inventory.reserve','err',['retry'=>$n,'reason'=>substr($e->getMessage(),0,140)]);

            if ($n < $max) {
                $props = array_merge($m->get_properties(), RH::withRetryHeaders($m, $n+1));
                $props['expiration'] = (string) RH::computeDelayMs($n);
                $retryMsg = new \PhpAmqpLib\Message\AMQPMessage($m->getBody(), $props);
                $ch->basic_publish($retryMsg, '', 'inventory.retry');
            } else {
                $pub->publish('inventory.dlx','inventory.reserve.dlq',[
                    'type'=>'inventory.reserve.failed','v'=>1,
                    'message_id'=>(string) Str::uuid(),'occurred_at'=>now()->toISOString(),
                    'error'=>substr($e->getMessage(),0,200),
                    'payload'=>json_decode($m->getBody(), true),
                ]);
            }
            $m->ack();
        };

        $cb = function (AMQPMessage $m) use ($pub, $onFail) {
            $props = $m->get_properties();
            Telemetry::span('inventory.reserve.consume', function () use ($m, $props, $pub, $onFail) {
                try {
                    $raw = $m->getBody();
                    if (($props['content_encoding'] ?? null) === 'gzip') { $raw = @gzdecode($raw) ?: $raw; }
                    $p = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE) ?? [];
                    EventSchema::validate($p);

                    $data = $p['data'] ?? null; $mid = $p['message_id'] ?? null;
                    if (!$data || empty($data['items'])) { $m->ack(); return; }

                    if ($mid) {
                        $ins = DB::table('processed_messages')->insertOrIgnore([
                            'message_id'=>$mid,'consumer'=>'inventory','processed_at'=>now(),
                        ]);
                        if ($ins===0) { $m->ack(); return; }
                    }

                    DB::transaction(function () use ($data) {
                        foreach ($data['items'] as $i) {
                            $sku = $i['sku']; $qty = (int) $i['qty'];
                            $p = Product::where('sku',$sku)->lockForUpdate()->firstOrFail();
                            if ($p->stock_qty - $p->reserved_qty < $qty) throw new \RuntimeException("insufficient stock for {$sku}");
                            $p->reserved_qty += $qty; $p->save();
                            Reservation::updateOrCreate(
                                ['order_id'=>$data['order_id']??null,'product_id'=>$p->id],
                                ['qty'=>$qty,'status'=>'RESERVED']
                            );
                        }
                    });

                    $evt = [
                        'type'=>'inventory.reserved','v'=>1,
                        'message_id'=>(string) Str::uuid(),'occurred_at'=>now()->toISOString(),
                        'data'=>$data,
                    ];
                    $hdr = ['x-causation-id'=>$mid,'x-correlation-id'=>$data['order_id']??null];
                    $pub->publish('inventory.x','inventory.reserved',$evt,$hdr);

                    Pulse::send('inventory','inventory.reserved','ok',[
                        'order_id'=>$data['order_id']??null,'items'=>count($data['items'])
                    ]);
                    $m->ack();
                } catch (\Throwable $e) {
                    logger()->warning('inventory.reserve failed', ['err'=>$e->getMessage()]);
                    $onFail($m, $e);
                }
            }, ['messaging.system'=>'rabbitmq','messaging.destination'=>'inventory.reserve.q']);
        };

        $tag = $ch->basic_consume($q, 'inventory', false, false, false, false, $cb);

        $running = true;
        if (\function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function() use (&$running, $ch, $tag) { $running = false; try { $ch->basic_cancel($tag); } catch (\Throwable) {} });
            pcntl_signal(SIGINT,  function() use (&$running, $ch, $tag) { $running = false; try { $ch->basic_cancel($tag); } catch (\Throwable) {} });
        }

        while ($running && $ch->is_consuming()) { $ch->wait(null, true, 1); }

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
