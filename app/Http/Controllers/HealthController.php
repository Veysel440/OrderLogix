<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class HealthController
{
    public function __invoke()
    {
        $db = 'ok';
        try { DB::select('SELECT 1'); } catch (\Throwable $e) { $db = 'err'; }

        $ctxs = ['orders','inventory','payments'];
        $amqp = [];
        foreach ($ctxs as $ctx) {
            try { $c = ConnectionFactory::connect($ctx); $ch = $c->channel(); $ch->close(); $c->close(); $amqp[$ctx] = 'ok'; }
            catch (\Throwable) { $amqp[$ctx] = 'err'; }
        }

        $mgmtReq = filter_var(env('HEALTH_REQUIRE_MGMT', true), FILTER_VALIDATE_BOOLEAN);
        $queuesReq = filter_var(env('HEALTH_REQUIRE_QUEUES', false), FILTER_VALIDATE_BOOLEAN);
        $mgmt = [];
        $queues = [];

        if ($mgmtReq) {
            $base = rtrim((string) env('RABBITMQ_MGMT_URL','http://127.0.0.1:15672'), '/');
            foreach ($ctxs as $ctx) {
                $vhost = $this->ctxVhost($ctx);
                $url = "{$base}/api/aliveness-test/".rawurlencode($vhost);
                try {
                    $r = Http::withBasicAuth(
                        (string) env('RABBITMQ_MGMT_USER','guest'),
                        (string) env('RABBITMQ_MGMT_PASSWORD','guest')
                    )->timeout((int) env('HEALTH_HTTP_TIMEOUT',2))->get($url);
                    $mgmt[$ctx] = ($r->ok() && $r->json('status') === 'ok') ? 'ok' : 'err';
                } catch (\Throwable) { $mgmt[$ctx] = 'err'; }
            }
        }

        if ($queuesReq) {
            $expected = array_filter(array_map('trim', explode(',', (string) env('HEALTH_EXPECT_QUEUES',''))));
            if ($expected) {
                try {
                    $c = ConnectionFactory::connect('orders'); $ch = $c->channel();
                    foreach ($expected as $q) {
                        try { $ch->queue_declare($q, false, true, false, false, true); $queues[$q]='ok'; }
                        catch (\Throwable) { $queues[$q]='missing'; }
                    }
                    $ch->close(); $c->close();
                } catch (\Throwable) { /* ignore */ }
            }
        }

        $overall = ($db === 'ok'
            && !in_array('err', array_values($amqp), true)
            && (! $mgmtReq || !in_array('err', array_values($mgmt), true))
            && (! $queuesReq || !in_array('missing', array_values($queues), true))
        ) ? 'ok' : 'degraded';

        return response()->json([
            'status' => $overall,
            'db'     => $db,
            'amqp'   => $amqp,
            'mgmt'   => $mgmtReq ? $mgmt : null,
            'queues' => $queuesReq ? $queues : null,
            'time'   => now()->toISOString(),
        ], $overall === 'ok' ? 200 : 503);
    }

    private function ctxVhost(string $ctx): string
    {
        $prefix = match ($ctx) {
            'orders'    => 'RMQ_ORDERS_',
            'inventory' => 'RMQ_INVENTORY_',
            'payments'  => 'RMQ_PAYMENTS_',
            default     => 'RABBITMQ_',
        };
        return (string) env($prefix.'VHOST', env('RABBITMQ_VHOST','/'));
    }
}
