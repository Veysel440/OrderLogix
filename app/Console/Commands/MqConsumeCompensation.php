<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Reservation;
use App\Support\Telemetry;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeCompensation extends Command
{
    protected $signature = 'mq:consume:compensation';
    protected $description = 'Consumes payment.failed, releases reservations, emits inventory.released';

    public function handle(): int
    {
        $q = 'payments.failed.q';

        $c  = ConnectionFactory::connect();
        $ch = $c->channel();
        $ch->basic_qos(null, 16, null);
        $pub = new Publisher($ch);

        $ch->exchange_declare('payments.x','topic',false,true,false);
        $ch->exchange_declare('inventory.x','topic',false,true,false);
        $ch->queue_declare($q, false, true, false, false, false, new \PhpAmqpLib\Wire\AMQPTable([
            'x-queue-type'=>'quorum',
        ]));
        $ch->queue_bind($q, 'payments.x', 'payment.failed');

        $this->info("compensation listening on {$q}");

        $cb = function(AMQPMessage $m) use ($pub) {
            Telemetry::span('compensation.consume', function() use ($m, $pub) {
                $ev   = json_decode($m->getBody(), true) ?? [];
                $data = $ev['data'] ?? [];
                $orderId = $data['order_id'] ?? null;
                if (!$orderId) { $this->line('skip: no order_id'); $m->ack(); return; }

                $mid = $ev['message_id'] ?? null;
                $ins = DB::table('processed_messages')->insertOrIgnore([
                    'message_id'=>$mid ?? (string) Str::uuid(),
                    'consumer'=>'compensation',
                    'processed_at'=>now(),
                ]);
                if ($ins === 0) { $this->line("dup: {$mid}"); $m->ack(); return; }

                $items = [];
                DB::transaction(function() use ($orderId, &$items) {
                    $resList = Reservation::query()
                        ->where('order_id', $orderId)
                        ->where('status','RESERVED')
                        ->lockForUpdate()
                        ->get();

                    foreach ($resList as $res) {
                        /** @var Reservation $res */
                        $p = Product::query()->lockForUpdate()->findOrFail($res->product_id);
                        $p->reserved_qty = max(0, $p->reserved_qty - $res->qty);
                        $p->save();

                        $res->status = 'RELEASED';
                        $res->save();

                        $items[] = ['product_id'=>$p->id,'sku'=>$p->sku,'qty'=>$res->qty];
                    }
                });

                if ($items) {
                    $pub->publish('inventory.x','inventory.released',[
                        'message_id'=>(string) Str::uuid(),
                        'type'=>'inventory.released',
                        'occurred_at'=>now()->toISOString(),
                        'data'=>['order_id'=>$orderId,'items'=>$items],
                    ], ['x-causation-id'=>$mid]);
                    $this->line("→ inventory.released order={$orderId} items=".count($items));
                } else {
                    $this->line("no RESERVED rows to release for order={$orderId}");
                }

                $m->ack();
            }, [
                'messaging.system'=>'rabbitmq',
                'messaging.destination'=>'payments.failed.q',
            ]);
        };

        $ch->basic_consume($q,'',false,false,false,false,$cb);
        while ($ch->is_consuming()) { $ch->wait(); }
        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
