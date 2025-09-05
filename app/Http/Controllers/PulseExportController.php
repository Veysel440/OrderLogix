<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Pulse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

final class PulseExportController
{
    public function events(Request $r)
    {
        $key = env('PULSE_REDIS_LIST','pulse:events');
        $n   = min( (int) $r->integer('limit', 1000), 20000);
        $raw = Redis::lrange($key, 0, $n-1) ?? [];
        $out = array_map(static fn($x) => json_decode($x, true), $raw);

        return response()->json($out);
    }

    public function snapshot()
    {
        return response()->json([
            'ts'     => now()->toISOString(),
            'config' => [
                'channel' => Pulse::$channel,
                'list'    => env('PULSE_REDIS_LIST','pulse:events'),
                'max'     => (int) env('PULSE_REDIS_MAX', 20000),
            ],
        ]);
    }
}
