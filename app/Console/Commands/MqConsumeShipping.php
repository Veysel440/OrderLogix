<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumeShipping extends Command
{
    protected $signature = 'mq:consume:shipping';
    protected $description = 'Consumes payment.authorized, schedules shipping, emits shipping.scheduled';

    public function handle(): int
    {
        $q = 'shipping.schedule.q';
        $c = ConnectionFactory::connect();
        $ch = $c->channel();
        $ch->basic_qos(null, 16, null);
        $pub = new Publisher($ch);

        $ch->exchange_declare('payments.x','topic',false,true,false);
        $ch->exchange_declare('shipping.x','topic',false,true,false);
        $ch->queue_declare($q,false,true,false,false);
        $ch->queue_bind($q,'payments.x','payment.authorized');

        $this->info("shipping listening on {$q}");

        $cb = function(AMQPMessage $m) use ($pub) {
            $ev = json_decode($m->getBody(), true) ?? [];
            $data = $ev['data'] ?? [];
            $orderId = $data['order_id'] ?? null;
            if (!$orderId) { $m->ack(); return; }

            $scheduledAt = now()->addDay();

            Shipment::updateOrCreate(
                ['order_id'=>$orderId],
                ['status'=>'SCHEDULED','scheduled_at'=>$scheduledAt]
            );

            $out = [
                'message_id'  => (string) Str::uuid(),
                'type'        => 'shipping.scheduled',
                'occurred_at' => now()->toISOString(),
                'data'        => ['order_id'=>$orderId,'scheduled_at'=>$scheduledAt->toISOString()],
            ];
            $pub->publish('shipping.x','shipping.scheduled',$out,['x-causation-id'=>$ev['message_id'] ?? null]);

            $this->line("→ shipping.scheduled order={$orderId}");
            $m->ack();
        };

        $ch->basic_consume($q,'',false,false,false,false,$cb);
        while ($ch->is_consuming()) { $ch->wait(); }
        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
