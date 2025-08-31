<?php

namespace App\Support;

final class Telemetry
{
    private static bool $enabled = false;
    private static $tracer = null;

    public static function init(): void
    {
        self::$enabled = (bool) env('OTEL_ENABLED', false);
        if (!self::$enabled) return;

        if (!class_exists(\OpenTelemetry\SDK\Trace\TracerProvider::class)) {
            self::$enabled = false; return;
        }
        $service = env('OTEL_SERVICE_NAME', 'orderlogix');

        $exporter = new \OpenTelemetry\Contrib\Otlp\Exporter(
            endpoint: rtrim(env('OTEL_EXPORTER_OTLP_ENDPOINT','http://localhost:4318'), '/').'/v1/traces',
            contentType: 'application/json'
        );
        $spanProcessor = new \OpenTelemetry\SDK\Trace\SimpleSpanProcessor($exporter);
        $tp = new \OpenTelemetry\SDK\Trace\TracerProvider($spanProcessor);
        self::$tracer = $tp->getTracer($service);
    }

    /**
     * @param array<string,scalar|\Stringable> $attrs
     */
    public static function span(string $name, callable $fn, array $attrs = [])
    {
        if (!self::$enabled || !self::$tracer) return $fn();
        $span = self::$tracer->spanBuilder($name)->startSpan();
        foreach ($attrs as $k => $v) { $span->setAttribute($k, (string) $v); }
        try {
            $res = $fn();
            $span->end();
            return $res;
        } catch (\Throwable $e) {
            $span->recordException($e); $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
            $span->end(); throw $e;
        }
    }
}
