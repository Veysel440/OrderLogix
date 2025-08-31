<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Rabbit\ConnectionFactory;
use App\Services\Rabbit\Publisher;
use App\Services\Rabbit\RpcClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumePaymentAuthorize extends Command
{
    protected $signature = 'mq:consume:payment-authorize';
    protected $description = 'Consumes inventory.reserved, calls payments RPC, emits payment.authorized|failed';

    public function handle(): int
    {
        $q = 'inventory.reserved.q';
        $c = ConnectionFactory::connect();
        $ch = $c->channel();
        $ch->basic_qos(null, 8, null);
        $pub = new Publisher($ch);
        $rpc = new RpcClient($ch);

        $ch->exchange_declare('inventory.x','topic',false,true,false);
        $ch->queue_declare($q,false,true,false,false);
        $ch->queue_bind($q,'inventory.x','inventory.reserved');

        $this->info("payment-authorize listening on {$q}");

        $cb = function(AMQPMessage $m) use ($rpc,$pub) {
            $ev = json_decode($m->getBody(), true) ?? [];
            $data = $ev['data'] ?? [];
            $orderId = $data['order_id'] ?? null;
            if (!$orderId) { $m->ack(); return; }

            $order = Order::find($orderId);
            if (!$order) { $m->ack(); return; }

            $resp = $rpc->callAuthorize(['order_id'=>$orderId,'amount'=>(float)$order->total,'currency'=>$order->currency], 5.0);

            DB::transaction(function() use ($resp,$order) {
                $status = $resp['authorized'] ? 'AUTHORIZED' : 'FAILED';
                Payment::create([
                    'order_id' => $order->id,
                    'amount'   => $order->total,
                    'currency' => $order->currency,
                    'status'   => $status,
                    'provider' => $resp['provider'] ?? 'mock',
                    'provider_ref' => $resp['auth_code'] ?? null,
                    'meta' => $resp,
                ]);
            });

            $type = $resp['authorized'] ? 'payment.authorized' : 'payment.failed';
            $pub->publish('payments.x', $type, [
                'message_id'  => (string) Str::uuid(),
                'type'        => $type,
                'occurred_at' => now()->toISOString(),
                'data'        => ['order_id'=>$orderId, 'amount'=>(float)$order->total, 'currency'=>$order->currency],
            ], ['x-causation-id' => $ev['message_id'] ?? null]);

            $this->line("→ {$type} for order={$orderId}");
            $m->ack();
        };

        $ch->basic_consume($q,'',false,false,false,false,$cb);
        while ($ch->is_consuming()) { $ch->wait(); }
        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
