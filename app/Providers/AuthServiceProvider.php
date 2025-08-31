<?php declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [

    ];

    public function boot(): void
    {

        Gate::define('view-metrics', function ($user = null) {

            return true;
        });

        Gate::define('view-health', fn($user = null) => true);
    }
}
