<?php declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class Trace
{
    public static function currentTraceparent(?Request $r = null): ?string
    {
        $r ??= request();
        $tp = $r?->header('traceparent') ?: $r?->headers->get('Traceparent');
        return is_string($tp) && $tp !== '' ? $tp : null;
    }

    /** @param array<string,mixed> $amqpProps */
    public static function fromAmqpHeaders(array $amqpProps): ?string
    {
        $hdr = $amqpProps['application_headers'] ?? null;
        if (is_object($hdr) && method_exists($hdr, 'getNativeData')) {
            $d = $hdr->getNativeData();
            $tp = $d['traceparent'] ?? null;
            return is_string($tp) && $tp !== '' ? $tp : null;
        }
        return null;
    }

    public static function currentRequestId(?Request $r = null): ?string
    {
        $r ??= request();
        $rid = $r?->headers->get('X-Request-Id') ?? $r?->attributes->get('request_id');
        return is_string($rid) && $rid !== '' ? $rid : null;
    }
}
