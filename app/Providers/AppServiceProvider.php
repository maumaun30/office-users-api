<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-users', fn (User $user) => $user->canViewUsers());
        Gate::define('view-user', fn (User $user, User $target) => $user->canViewUsers() || $user->is($target));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by(
            strtolower((string) $request->input('email')).'|'.$request->ip()
        ));
    }
}
