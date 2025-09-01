<?php declare(strict_types=1);

namespace App\Services\Rabbit;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;

final class ConnectionFactory
{
    public static function connect(string $ctx = 'orders')
    {
        $prefix = match ($ctx) {
            'orders'    => 'RMQ_ORDERS_',
            'inventory' => 'RMQ_INVENTORY_',
            'payments'  => 'RMQ_PAYMENTS_',
            default     => 'RABBITMQ_',
        };

        $host   = env('RABBITMQ_HOST', '127.0.0.1');
        $user   = env($prefix.'USER',     env('RABBITMQ_USER', 'guest'));
        $pass   = env($prefix.'PASSWORD', env('RABBITMQ_PASSWORD', 'guest'));
        $vhost  = env($prefix.'VHOST',    env('RABBITMQ_VHOST', '/'));

        $conn_to = (float) env('AMQP_CONN_TIMEOUT', 3.0);
        $rw_to   = (float) env('AMQP_RW_TIMEOUT', 3.0);
        $keep    = (bool)  env('AMQP_KEEPALIVE', true);
        $hb      = (int)   env('AMQP_HEARTBEAT', 30);
        $useTls  = filter_var(env('AMQP_TLS', false), FILTER_VALIDATE_BOOLEAN);

        $clientProps = [
            'connection_name' => sprintf('orderlogix:%s', $ctx),
            'product' => 'orderlogix-app',
        ];

        if ($useTls) {
            $port   = (int) env('RABBITMQ_TLS_PORT', 5671);
            $verify = filter_var(env('AMQP_TLS_VERIFY', false), FILTER_VALIDATE_BOOLEAN);

            $sslOpts = [
                'verify_peer'      => $verify,
                'verify_peer_name' => $verify,
            ];
            if ($ca  = env('AMQP_TLS_CA'))   { $sslOpts['cafile']     = $ca; }
            if ($crt = env('AMQP_TLS_CERT')) { $sslOpts['local_cert'] = $crt; }
            if ($key = env('AMQP_TLS_KEY'))  { $sslOpts['local_pk']   = $key; }
            if ($verify) {
                $sslOpts['peer_name'] = env('AMQP_TLS_PEER_NAME', $host);
            }

            $options = [
                'connection_timeout' => $conn_to,
                'read_write_timeout' => $rw_to,
                'keepalive'          => $keep,
                'heartbeat'          => $hb,
                'client_properties'  => $clientProps,
            ];

            return new AMQPSSLConnection($host, $port, $user, $pass, $vhost, $sslOpts, $options);
        }

        $port = (int) env('RABBITMQ_PORT', 5672);

        $options = [
            'connection_timeout' => $conn_to,
            'read_write_timeout' => $rw_to,
            'keepalive'          => $keep,
            'heartbeat'          => $hb,
            'client_properties'  => $clientProps,
        ];

        return AMQPStreamConnection::create_connection([[
            'host'               => $host,
            'port'               => $port,
            'user'               => $user,
            'password'           => $pass,
            'vhost'              => $vhost,
            'login_method'       => 'AMQPLAIN',
            'locale'             => 'en_US',
            'connection_timeout' => $conn_to,
            'read_write_timeout' => $rw_to,
            'keepalive'          => $keep,
            'heartbeat'          => $hb,
            'client_properties'  => $clientProps,
        ]]);
    }
}
