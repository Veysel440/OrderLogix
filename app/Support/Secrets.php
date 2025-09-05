<?php declare(strict_types=1);

namespace App\Support;

final class Secrets
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $fileKey = $key . '_FILE';
        $path    = env($fileKey);

        if (is_string($path) && $path !== '' && is_readable($path)) {
            $val = trim((string) @file_get_contents($path));
            return $val !== '' ? $val : $default;
        }

        $v = env($key);
        return $v !== null ? (string) $v : $default;
    }
}
