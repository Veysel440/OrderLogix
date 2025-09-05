<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Pulse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

final class PulseExportController
{
    public function events(Request $r)
    {
        $key = (string) env('PULSE_REDIS_LIST', 'pulse:events');
        $n   = max(1, min((int) $r->integer('limit', 1000), (int) env('PULSE_REDIS_MAX', 20000)));

        $raw = Redis::connection()->lrange($key, 0, $n - 1) ?? [];
        $out = [];
        foreach ($raw as $x) {
            $d = json_decode((string) $x, true);
            if (is_array($d)) { $out[] = $d; }
        }

        return response()->json($out);
    }

    public function snapshot()
    {
        return response()->json([
            'ts'     => now()->toISOString(),
            'config' => [
                'channel' => Pulse::$channel,
                'list'    => (string) env('PULSE_REDIS_LIST', 'pulse:events'),
                'max'     => (int) env('PULSE_REDIS_MAX', 20000),
            ],
        ]);
    }
}
