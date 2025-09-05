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
        $retryMs   = max(1000, (int) $r->integer('retry', 3000));
        $heartbeat = max(5, (int) $r->integer('heartbeat', 10));

        $callback = function () use ($retryMs, $heartbeat) {
            echo "retry: {$retryMs}\n\n";
            @ob_flush(); @flush();

            $conn = Redis::connection();
            $client = $conn->client();

            $client->psubscribe([Pulse::$channel]);

            $lastBeat = time();

            try {
                foreach ($client as $msg) {
                    if (connection_aborted()) {
                        break;
                    }

                    if (is_array($msg) && ($msg[0] ?? '') === 'pmessage') {
                        $payload = (string) ($msg[3] ?? '{}');
                        echo "data: {$payload}\n\n";
                        @ob_flush(); @flush();
                        $lastBeat = time();
                        continue;
                    }

                    if (time() - $lastBeat >= $heartbeat) {
                        echo "event: ping\ndata: {}\n\n";
                        @ob_flush(); @flush();
                        $lastBeat = time();
                    }
                }
            } catch (\Throwable $e) {
                $err = json_encode(['error' => 'stream_ended', 'reason' => substr($e->getMessage(), 0, 200)]);
                echo "event: error\ndata: {$err}\n\n";
                @ob_flush(); @flush();
            } finally {
                try { $client->punsubscribe([Pulse::$channel]); } catch (\Throwable) {}
            }
        };

        return response()->stream($callback, 200, [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
