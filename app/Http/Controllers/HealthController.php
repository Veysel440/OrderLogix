<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Rabbit\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController
{
    public function __invoke()
    {
        $db = 'ok';
        try { DB::select('SELECT 1'); } catch (\Throwable $e) { $db = 'err'; }

        $ctxs = ['orders','inventory','payments'];

        $amqp = [];
        foreach ($ctxs as $ctx) {
            try { $c = ConnectionFactory::connect($ctx); $ch = $c->channel(); $ch->close(); $c->close(); $amqp[$ctx] = 'ok'; }
            catch (\Throwable $e) { $amqp[$ctx] = 'err'; }
        }

        $mgmt = [];
        $base = rtrim((string) env('RABBITMQ_MGMT_URL','http://127.0.0.1:15672'), '/');
        foreach ($ctxs as $ctx) {
            [$user,$pass,$vhost] = $this->ctxCreds($ctx);
            $url = "{$base}/api/aliveness-test/".rawurlencode($vhost);
            try {
                $r = Http::withBasicAuth(
                    (string) env('RABBITMQ_MGMT_USER', $user),
                    (string) env('RABBITMQ_MGMT_PASSWORD', $pass)
                )->timeout((int) env('HEALTH_HTTP_TIMEOUT', 2))->get($url);
                $mgmt[$ctx] = ($r->ok() && $r->json('status') === 'ok') ? 'ok' : 'err';
            } catch (\Throwable $e) {
                $mgmt[$ctx] = 'err';
            }
        }

        $overall = ($db === 'ok'
            && !in_array('err', array_values($amqp), true)
            && !in_array('err', array_values($mgmt), true)) ? 'ok' : 'degraded';

        return response()->json([
            'status' => $overall,
            'db'     => $db,
            'amqp'   => $amqp,
            'mgmt'   => $mgmt,
            'time'   => now()->toISOString(),
        ], $overall === 'ok' ? 200 : 503);
    }

    /** @return array{0:string,1:string,2:string} */
    private function ctxCreds(string $ctx): array
    {
        $prefix = match ($ctx) {
            'orders'    => 'RMQ_ORDERS_',
            'inventory' => 'RMQ_INVENTORY_',
            'payments'  => 'RMQ_PAYMENTS_',
            default     => 'RABBITMQ_',
        };
        $user  = (string) env($prefix.'USER',     env('RABBITMQ_USER','guest'));
        $pass  = (string) env($prefix.'PASSWORD', env('RABBITMQ_PASSWORD','guest'));
        $vhost = (string) env($prefix.'VHOST',    env('RABBITMQ_VHOST','/'));
        return [$user,$pass,$vhost];
    }
}
