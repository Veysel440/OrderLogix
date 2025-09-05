<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Pulse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PulseStreamController
{
    public function __invoke(Request $r): StreamedResponse
    {
        $retryMs = (int) $r->integer('retry', 3000);

        return response()->stream(function () use ($retryMs) {
            @ob_end_flush();
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            echo "retry: {$retryMs}\n\n"; @flush();

            $redis = Redis::connection();
            $sub   = $redis->client();
            $sub->psubscribe([Pulse::$channel]);

            $lastBeat = time();
            foreach ($sub as $msg) {
                if (!is_array($msg) || ($msg[0] ?? '') !== 'pmessage') {
                    // heartbeat (her 10 sn)
                    if (time() - $lastBeat >= 10) { echo "event: ping\ndata: {}\n\n"; @flush(); $lastBeat = time(); }
                    continue;
                }
                $payload = (string) ($msg[3] ?? '{}');
                echo "data: {$payload}\n\n";
                @flush();
                $lastBeat = time();
            }
        });
    }
}
