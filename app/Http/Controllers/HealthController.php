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
        try { DB::select('SELECT 1'); } catch (\Throwable $e) { $db = 'err: '.$e->getCode(); }

        $amqp = 'ok';
        try {
            $c = ConnectionFactory::connect();
            $c->close();
        } catch (\Throwable $e) { $amqp = 'err: '.$e->getCode(); }

        $mgmt = 'ok';
        try {
            $vhost = urlencode(env('RABBITMQ_VHOST','/'));
            $url = rtrim(env('RABBITMQ_MGMT_URL','http://127.0.0.1:15672'),'/')."/api/aliveness-test/{$vhost}";
            $resp = Http::withBasicAuth(env('RABBITMQ_MGMT_USER','guest'), env('RABBITMQ_MGMT_PASSWORD','guest'))
                ->timeout(2)->get($url);
            $mgmt = $resp->ok() ? 'ok' : 'http:'.$resp->status();
        } catch (\Throwable $e) { $mgmt = 'err'; }

        $overall = ($db==='ok' && $amqp==='ok' && $mgmt==='ok') ? 'ok' : 'degraded';

        return response()->json([
            'status'=>$overall,
            'db'=>$db,
            'amqp'=>$amqp,
            'mgmt'=>$mgmt,
            'time'=>now()->toISOString(),
        ], $overall==='ok'?200:503);
    }
}
