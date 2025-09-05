<?php declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        $this->configureRateLimiting();

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));

        Route::middleware('web')
            ->group(base_path('routes/web.php'));
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $id = (string)($request->user()->id ?? $request->ip());
            $perMin  = (int) env('RL_API_PER_MIN', 60);
            $perHour = (int) env('RL_API_PER_HOUR', 1000);

            return [
                Limit::perMinute($perMin)->by($id),
                Limit::perHour($perHour)->by($id),
            ];
        });

        RateLimiter::for('ops', function (Request $request) {
            $key = $request->header('X-Api-Key')
                ?: (string)($request->user()->id ?? $request->ip());

            $perMin = (int) env('RL_OPS_PER_MIN', 20);

            return [
                Limit::perMinute($perMin)
                    ->by($key)
                    ->response(function () {
                        return response()->json(['error' => 'rate_limited'], 429);
                    }),
            ];
        });
    }
}
