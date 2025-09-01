<?php declare(strict_types=1);

namespace App\Services\Rabbit;

use App\Support\EventSchema;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class Publisher
{
    private AMQPChannel $ch;
    private ?array $lastReturn = null;
    private bool $confirms = false;

    public function __construct(AMQPChannel $ch)
    {
        $this->ch = $ch;

        $this->ch->confirm_select();
        $this->confirms = true;

        $this->ch->set_ack_handler(static function ($deliveryTag, $multiple) {
        });
        $this->ch->set_nack_handler(static function ($deliveryTag, $multiple, $requeue) {
        });
        $this->ch->set_return_listener(function ($replyCode, $replyText, $exchange, $routingKey, AMQPMessage $msg) {
            $this->lastReturn = [
                'code' => $replyCode,
                'text' => $replyText,
                'x'    => $exchange,
                'rk'   => $routingKey,
                'body' => $msg->getBody(),
            ];
        });
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $headers
     */
    public function publish(string $exchange, string $routingKey, array $payload, array $headers = []): void
    {
        $payload['type']        = $payload['type']        ?? $routingKey;
        $payload['v']           = $payload['v']           ?? 1;
        $payload['occurred_at'] = $payload['occurred_at'] ?? now()->toISOString();
        $payload['message_id']  = $payload['message_id']  ?? (string) Str::uuid();

        EventSchema::validate($payload);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('json encode failed');
        }

        $props = [
            'content_type'  => 'application/json',
            'delivery_mode' => 2,
            'message_id'    => $payload['message_id'],
            'type'          => $payload['type'],
        ];

        $threshold = (int) env('AMQP_COMPRESS_THRESHOLD', 100 * 1024);
        if (strlen($body) > $threshold) {
            $gz = gzencode($body, 6);
            if ($gz !== false) {
                $body = $gz;
                $props['content_encoding'] = 'gzip';
            }
        }

        $tp = request()?->header('traceparent');
        if ($tp) { $headers['traceparent'] = $tp; }
        if (!empty($payload['data']['order_id'])) {
            $headers['x-order-id'] = (string) $payload['data']['order_id'];
        }
        $props['application_headers'] = new AMQPTable($headers);

        $msg = new AMQPMessage($body, $props);

        $this->lastReturn = null;
        $this->ch->basic_publish($msg, $exchange, $routingKey, true, false);

        if ($this->confirms) {
            $timeout = (float) env('PUBLISH_CONFIRM_TIMEOUT', 3.0);
            try {
                $this->ch->wait_for_pending_acks_returns($timeout);
            } catch (\Throwable $e) {
                throw new \RuntimeException('publish confirm error: '.$e->getMessage(), previous: $e);
            }
        }

        if ($this->lastReturn !== null) {
            throw new \RuntimeException(
                sprintf('unroutable [%s→%s]: %s', $exchange, $routingKey, $this->lastReturn['text'] ?? 'return')
            );
        }
    }
}
