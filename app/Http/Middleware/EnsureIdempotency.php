<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            /** @var Response $resp */
            $resp = $next($request);
            return $resp;
        }

        $scope  = $request->method() . ' ' . $request->route()->uri();
        $userId = optional($request->user())->id ?? 0;

        $hash = hash('sha256', json_encode($request->all(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $ttlHours = (int) env('IDEMPOTENCY_TTL_HOURS', 24);

        $inserted = (bool) DB::table('idempotency_keys')->insertOrIgnore([[
            'key'          => $key,
            'scope'        => $scope,
            'user_id'      => $userId,
            'request_hash' => $hash,
            'response_code'=> null,
            'response_body'=> null,
            'created_at'   => now(),
            'expires_at'   => $ttlHours > 0 ? now()->addHours($ttlHours) : null,
        ]]);

        if (!$inserted) {
            $row = DB::table('idempotency_keys')->where([
                'key' => $key, 'scope' => $scope, 'user_id' => $userId,
            ])->first();

            if (!$row) {
                return response()->json(['error' => 'idempotency_inconsistent'], 409);
            }
            if ($row->request_hash !== $hash) {
                return response()->json(['error' => 'idempotency_conflict'], 409);
            }
            if (!is_null($row->response_code)) {
                return response((string) $row->response_body, (int) $row->response_code)
                    ->header('Content-Type', 'application/json')
                    ->header('Idempotent-Replay', 'true');
            }
            return response()->json(['status' => 'processing'], 409);
        }

        /** @var Response $resp */
        $resp = $next($request);

        try {
            DB::table('idempotency_keys')->where([
                'key' => $key, 'scope' => $scope, 'user_id' => $userId,
            ])->update([
                'response_code' => $resp->getStatusCode(),
                'response_body' => ($resp->headers->get('Content-Type') === 'application/json')
                    ? (string) $resp->getContent()
                    : json_encode(['non_json' => true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable) { /* ignore */ }

        return $resp->header('Idempotent-Stored', 'true');
    }
}
