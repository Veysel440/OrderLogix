<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class MqConsumePaymentsRpc extends Command
{
    protected $signature = 'mq:consume:payments-rpc';
    protected $description = 'RPC server: handles payment.authorize.request';

    public function handle(): int
    {
        $q = 'payments.auth.q';
        $c = ConnectionFactory::connect();
        $ch = $c->channel();
        $ch->basic_qos(null, 8, null);

        $this->info("payments RPC listening on {$q}");

        $cb = function(AMQPMessage $m) {
            $req = json_decode($m->getBody(), true) ?? [];
            $amount = (float)($req['amount'] ?? 0);
            $orderId = $req['order_id'] ?? null;

            $ok = $amount > 0 && $amount <= 500.00;

            $resp = [
                'order_id'  => $orderId,
                'authorized'=> $ok,
                'provider'  => 'mock',
                'auth_code' => $ok ? substr(md5((string)microtime(true)),0,8) : null,
                'error'     => $ok ? null : 'limit_exceeded',
            ];

            $props = [
                'correlation_id' => $m->get('correlation_id'),
                'content_type'   => 'application/json',
                'delivery_mode'  => 1,
            ];
            $reply = new AMQPMessage(json_encode($resp, JSON_UNESCAPED_UNICODE), $props);
            $m->getChannel()->basic_publish($reply, '', $m->get('reply_to'));

            $m->ack();
        };

        $ch->basic_consume($q, '', false, false, false, false, $cb);
        while ($ch->is_consuming()) { $ch->wait(); }

        $ch->close(); $c->close();
        return self::SUCCESS;
    }
}
