<?php

return [
    'paths' => ['api/*', 'pulse/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET','POST','OPTIONS'],
    'allowed_origins' => array_filter(preg_split(
        '/\s*,\s*/',
        env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'),
        -1, PREG_SPLIT_NO_EMPTY
    )),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization','Content-Type','Accept','X-Request-Id','traceparent'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
