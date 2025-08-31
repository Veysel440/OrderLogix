<?php

namespace App\Services\Rabbit;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class Publisher
{
    public function __construct(private readonly \PhpAmqpLib\Channel\AMQPChannel $ch){}

    public function publish(string $exchange, string $routingKey, array $payload, array $headers = []): void
    {
        $msg = new AMQPMessage(
            json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            [
                'content_type' => 'application/json',
                'delivery_mode' => 2,
                'message_id' => $payload['message_id'] ?? null,
                'type' => $payload['type'] ?? $routingKey,
                'application_headers' => new AMQPTable($headers),
            ]
        );
        $this->ch->basic_publish($msg, $exchange, $routingKey);
    }
}
