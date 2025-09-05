<?php declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

final class Pulse
{
    public static string $channel = 'pulse.stream';

    /**
     * @param array<string,mixed> $meta
     */
    public static function send(string $kind, string $name, string $status = 'ok', array $meta = []): void
    {
        $evt = [
            'id'     => (string) Str::uuid(),
            'ts'     => now()->toISOString(),
            'kind'   => $kind,
            'name'   => $name,
            'status' => $status,
            'meta'   => $meta,
        ];

        $json = json_encode($evt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $r = Redis::connection();

            $r->publish(self::$channel, $json);

            $list = env('PULSE_REDIS_LIST', 'pulse:events');
            $max  = max(1000, (int) env('PULSE_REDIS_MAX', 20000));
            $r->lPush($list, $json);
            $r->lTrim($list, 0, $max - 1);
        } catch (\Throwable $e) {
            logger()->warning('pulse.send failed', ['err' => $e->getMessage()]);
        }
    }
}
