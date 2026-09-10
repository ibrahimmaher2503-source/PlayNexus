<?php

namespace App\Providers;

use App\Models\Branch;
use App\Policies\BranchPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Gate::policy(Branch::class, BranchPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower(trim(is_string($email) ? $email : '')).'|'.$request->ip()),
            );
        });
    }
}
