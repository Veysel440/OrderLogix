<?php

namespace App\Services\Rabbit;


use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class RetryHelper
{
    public static function retryCount(AMQPMessage $m): int {
        $h = $m->get_properties()['application_headers'] ?? null;
        if ($h instanceof AMQPTable) return (int) (($h->getNativeData()['x-retry-count'] ?? 0));
        return 0;
    }
    public static function withRetryHeaders(AMQPMessage $m, int $n): array {
        $h = $m->get_properties()['application_headers'] ?? new AMQPTable();
        $data = $h instanceof AMQPTable ? $h->getNativeData() : [];
        $data['x-retry-count'] = $n;
        return ['application_headers' => new AMQPTable($data)];
    }
    public static function computeDelayMs(int $n): int {
        $base  = (int) env('RETRY_BASE_MS', 5000);
        $fact  = (int) env('RETRY_FACTOR', 2);
        $cap   = (int) env('RETRY_MAX_MS', 300000);
        $jrate = (float) env('RETRY_JITTER', 0.2);
        $nominal = min($cap, (int) round($base * ($fact ** $n)));
        $delta = (int) round($nominal * $jrate);
        return max(0, $nominal + random_int(-$delta, $delta));
    }
}
