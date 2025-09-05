<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $rid = (string) ($request->headers->get('X-Request-Id') ?: Str::ulid());
        $request->attributes->set('request_id', $rid);

        Log::withContext([
            'request_id' => $rid,
            'method'     => $request->method(),
            'path'       => '/'.$request->path(),
            'ip'         => $request->ip(),
            'traceparent'=> $request->headers->get('traceparent'),
        ]);

        /** @var Response $resp */
        $resp = $next($request);
        $resp->headers->set('X-Request-Id', $rid);
        return $resp;
    }
}
