<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\EloquentTaskRepository;
use App\Repositories\TaskRepositoryInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TaskRepositoryInterface::class, EloquentTaskRepository::class);

        if ($this->app->environment('local', 'testing')) {
            // test specific bindings
        }

        if ($this->app->environment('production')) {
            // production specific bindings
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?? $request->ip());
        });

        if ($this->app->environment('production')) {
            // Force HTTPS in production
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
