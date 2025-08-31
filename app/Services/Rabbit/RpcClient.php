<?php declare(strict_types=1);

namespace App\Services\Rabbit;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Str;

final class RpcClient
{
    public function __construct(private AMQPChannel $ch) {}

    /**
     * @return array{authorized:bool, provider?:string, auth_code?:?string, error?:?string, order_id?:mixed}
     */
    public function callAuthorize(array $payload, float $timeout = 5.0): array
    {
        [$callbackQueue,,] = $this->ch->queue_declare('', false, false, true, true);
        $corr = (string) Str::uuid();
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $resp = null;

        $this->ch->basic_consume($callbackQueue, '', false, true, false, false, function(AMQPMessage $m) use ($corr, &$resp) {
            if ($m->get('correlation_id') === $corr) {
                $resp = json_decode($m->getBody(), true) ?? [];
            }
        });

        $msg = new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode'=> 2,
            'reply_to'     => $callbackQueue,
            'correlation_id'=> $corr,
        ]);
        $this->ch->basic_publish($msg, 'payments.x', 'payment.authorize.request');

        $start = microtime(true);
        while ($resp === null) {
            $now = microtime(true);
            $left = $timeout - ($now - $start);
            if ($left <= 0) break;
            $this->ch->wait(null, false, $left);
        }
        return $resp ?? ['authorized'=>false, 'error'=>'rpc_timeout'];
    }
}
