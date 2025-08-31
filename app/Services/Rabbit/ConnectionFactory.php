<?php

namespace App\Services\Rabbit;

use PhpAmqpLib\Connection\AMQPStreamConnection;

final class ConnectionFactory
{
    public static function connect(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            env('RABBITMQ_HOST','127.0.0.1'),
            (int) env('RABBITMQ_PORT',5672),
            env('RABBITMQ_USER','guest'),
            env('RABBITMQ_PASSWORD','guest'),
            env('RABBITMQ_VHOST','/')
        );
    }
}
