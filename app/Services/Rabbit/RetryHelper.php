<?php

namespace App\Services\Rabbit;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class RetryHelper
{
    public static function retryCount(AMQPMessage $m): int
    {
        $h = $m->get_properties()['application_headers'] ?? null;
        $n = 0;
        if ($h instanceof AMQPTable) {
            $arr = $h->getNativeData();
            $n = (int)($arr['x-retry-count'] ?? 0);
        }
        return $n;
    }

    public static function withRetryHeaders(AMQPMessage $m, int $n): array
    {
        $h = $m->get_properties()['application_headers'] ?? new AMQPTable();
        if ($h instanceof AMQPTable) {
            $d = $h->getNativeData();
            $d['x-retry-count'] = $n;
            return ['application_headers' => new AMQPTable($d)];
        }
        return ['application_headers' => new AMQPTable(['x-retry-count' => $n])];
    }

    public static function nextQueue(int $n): ?string
    {
        return match(true){
            $n === 0 => 'inventory.retry.5s',
            $n === 1 => 'inventory.retry.30s',
            $n === 2 => 'inventory.retry.5m',
            default => null,
        };
    }
}
