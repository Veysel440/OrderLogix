<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Telemetry;
use App\Support\Pulse;
use App\Support\EventSchema;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

final class MqConsumeCompensation extends Command
{
    protected $signature = 'mq:consume:compensation';
    protected $description = 'On shipping.failed → emit inventory.release + payment.refund';

    public function handle(): int
    {
        $q = env('SHIPPING_FAILED_QUEUE', 'shipping.failed.q');
        $prefetch = (int) env('COMP_PREFETCH', 16);

        $cShip = ConnectionFactory::connect('shipping');
        $cInv  = ConnectionFactory::connect('inventory');
        $cPay  = ConnectionFactory::connect('payments');

        $chShip = $cShip->channel(); $chShip->basic_qos(null,$prefetch,null);
        $chInv  = $cInv->channel();
        $chPay  = $cPay->channel();

        $pubInv = new Publisher($chInv);
        $pubPay = new Publisher($chPay);

        $this->info("compensation ← {$q}");

        $cb = function (AMQPMessage $m) use ($pubInv, $pubPay) {
            Telemetry::span('compensation.consume', function () use ($m, $pubInv, $pubPay) {
                $p = json_decode($m->getBody(), true);
                EventSchema::validate($p); // shipping.failed

                $data = $p['data'] ?? [];
                $orderId = $data['order_id'] ?? null;

                $pubInv->publish('inventory.x','inventory.release',[
                    'type'=>'inventory.release','v'=>1,'message_id'=>(string) Str::uuid(),
                    'occurred_at'=>now()->toISOString(),
                    'data'=>['order_id'=>$orderId,'items'=>$data['items']??[]],
                ]);
                $pubPay->publish('payments.x','payment.refund',[
                    'type'=>'payment.refund','v'=>1,'message_id'=>(string) Str::uuid(),
                    'occurred_at'=>now()->toISOString(),
                    'data'=>['order_id'=>$orderId,'reason'=>'shipping_failed'],
                ]);

                Pulse::send('shipping','shipping.failed','err',['order_id'=>$orderId]);
                Pulse::send('inventory','inventory.release','ok',['order_id'=>$orderId]);
                Pulse::send('payments','payment.refund','ok',['order_id'=>$orderId]);

                $m->ack();
            }, ['messaging.system'=>'rabbitmq','messaging.destination'=>'shipping.failed.q']);
        };

        $chShip->basic_consume($q, '', false, false, false, false, $cb);
        while ($chShip->is_consuming()) { $chShip->wait(); }

        $chShip->close(); $cShip->close();
        $chInv->close();  $cInv->close();
        $chPay->close();  $cPay->close();

        return self::SUCCESS;
    }
}
