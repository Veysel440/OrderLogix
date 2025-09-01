<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if ($user = $request->user()) {
            $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
            if ($token && $token->can($ability)) {
                return $next($request);
            }
        }

        $plain = $request->header('X-Api-Key');
        if ($plain) {
            $hash = hash('sha256', $plain);
            /** @var ApiKey|null $key */
            $key = ApiKey::where('key_hash', $hash)->first();
            if ($key && (empty($key->abilities) || in_array($ability, $key->abilities, true))) {
                $key->forceFill(['last_used_at'=>now()])->saveQuietly();
                $request->attributes->set('api_key_id', $key->id);
                return $next($request);
            }
        }

        return response()->json(['error'=>'forbidden','need_ability'=>$ability], 403);
    }
}
