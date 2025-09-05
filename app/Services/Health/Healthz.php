<?php declare(strict_types=1);

namespace App\Services\Health;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpAmqpLib\Exception\AMQPIOException;

final class Healthz
{
    /**
     * @return array{
     *   status:string,
     *   db:string,
     *   amqp:array<string,string>,
     *   mgmt:array<string,string>,
     *   queues:array<string,array{ctx:string,messages:int|string,consumers:int|string}>,
     *   time:string
     * }
     */
    public static function check(): array
    {
        $now = now()->toISOString();

        $db = 'ok';
        try { DB::select('select 1'); } catch (\Throwable) { $db = 'err'; }

        $ctxs = array_values(array_filter(array_map('trim', explode(',', (string) env('HEALTH_CHECK_CONTEXTS', 'orders')))));

        $amqp = [];
        foreach ($ctxs as $ctx) {
            try {
                $c  = ConnectionFactory::connect($ctx);
                $ch = $c->channel();
                $ch->close(); $c->close();
                $amqp[$ctx] = 'ok';
            } catch (\Throwable) {
                $amqp[$ctx] = 'err';
            }
        }

        $mgmt          = [];
        $requireMgmt   = filter_var(env('HEALTH_REQUIRE_MGMT', true), FILTER_VALIDATE_BOOLEAN);
        $mgmtBase      = rtrim((string) env('RABBITMQ_MGMT_URL', 'http://127.0.0.1:15672'), '/');
        $httpTimeout   = (int) env('HEALTH_HTTP_TIMEOUT', 2);

        foreach ($ctxs as $ctx) {
            [$user, $pass, $vhost] = self::ctxCreds($ctx);
            $url = "{$mgmtBase}/api/aliveness-test/" . rawurlencode($vhost);

            try {
                $resp = Http::withBasicAuth(
                    (string) env('RABBITMQ_MGMT_USER', $user),
                    (string) env('RABBITMQ_MGMT_PASSWORD', $pass)
                )->timeout($httpTimeout)->get($url);

                $mgmt[$ctx] = ($resp->ok() && $resp->json('status') === 'ok') ? 'ok' : 'err';
            } catch (\Throwable) {
                $mgmt[$ctx] = 'err';
            }

            if (!$requireMgmt && $mgmt[$ctx] === 'err') {
                $mgmt[$ctx] = 'skip';
            }
        }

        $queues         = [];
        $requireQueues  = filter_var(env('HEALTH_REQUIRE_QUEUES', true), FILTER_VALIDATE_BOOLEAN);
        $expectedQueues = array_values(array_filter(array_map('trim', explode(',', (string) env('HEALTH_EXPECT_QUEUES', '')))));

        if ($expectedQueues) {
            foreach ($expectedQueues as $q) {
                $ctx = str_contains($q, 'payment') ? 'payments' : (str_contains($q, 'inventory') ? 'inventory' : 'orders');
                try {
                    $c  = ConnectionFactory::connect($ctx);
                    $ch = $c->channel();
                    [$name, $messages, $consumers] = $ch->queue_declare($q, true, false, false, false);
                    $ch->close(); $c->close();
                    $queues[$q] = ['ctx' => $ctx, 'messages' => $messages, 'consumers' => $consumers];
                } catch (AMQPIOException|\Throwable) {
                    $queues[$q] = ['ctx' => $ctx, 'messages' => 'err', 'consumers' => 'err'];
                }
            }
        }

        $okDb     = ($db === 'ok');
        $okAmqp   = !in_array('err', array_values($amqp), true);
        $okMgmt   = !in_array('err', array_values($mgmt), true);
        $okQueues = true;

        if ($expectedQueues) {
            foreach ($queues as $meta) {
                if ($meta['messages'] === 'err' || $meta['consumers'] === 'err') { $okQueues = false; break; }
            }
        }

        $overall = ($okDb && $okAmqp && ($requireMgmt ? $okMgmt : true) && ($requireQueues ? $okQueues : true))
            ? 'ok'
            : 'degraded';

        return [
            'status' => $overall,
            'db'     => $db,
            'amqp'   => $amqp,
            'mgmt'   => $mgmt,
            'queues' => $queues,
            'time'   => $now,
        ];
    }

    /** @return array{0:string,1:string,2:string} */
    private static function ctxCreds(string $ctx): array
    {
        $prefix = match ($ctx) {
            'orders'    => 'RMQ_ORDERS_',
            'inventory' => 'RMQ_INVENTORY_',
            'payments'  => 'RMQ_PAYMENTS_',
            default     => 'RABBITMQ_',
        };

        $user  = (string) env($prefix . 'USER',  env('RABBITMQ_USER', 'guest'));
        $pass  = (string) env($prefix . 'PASSWORD', env('RABBITMQ_PASSWORD', 'guest'));
        $vhost = (string) env($prefix . 'VHOST', env('RABBITMQ_VHOST', '/'));
        return [$user, $pass, $vhost];
    }
}
