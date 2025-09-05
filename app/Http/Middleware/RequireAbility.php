<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $required = preg_split('/[|, ]+/', trim($ability), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($user = $request->user()) {
            $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
            if ($token) {
                foreach ($required as $a) {
                    if ($token->can($a) || $token->can('*')) {
                        return $next($request);
                    }
                }
            }
        }

        $plain = $request->header('X-Api-Key')
            ?: (preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization'), $m) ? $m[1] : null);

        if ($plain) {
            $hash = hash('sha256', $plain);
            /** @var ApiKey|null $key */
            $key = ApiKey::where('key_hash', $hash)->first();
            if ($key) {
                $abilities = $key->abilities ?? [];
                $allowsAll = in_array('*', $abilities, true) || empty($abilities);
                $ok = $allowsAll || collect($required)->some(fn($a) => in_array($a, $abilities, true));

                if ($ok) {
                    $key->forceFill(['last_used_at' => now()])->saveQuietly();
                    $request->attributes->set('api_key_id', $key->id);
                    return $next($request);
                }
            }
        }

        return response()->json(['error' => 'forbidden', 'need_ability' => $required], 403);
    }
}
