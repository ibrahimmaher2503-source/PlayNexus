<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Guardian;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Policies\BranchPolicy;
use App\Policies\GuardianPolicy;
use App\Policies\PricingRulePolicy;
use App\Policies\TenantPolicy;
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
        Gate::policy(Guardian::class, GuardianPolicy::class);
        Gate::policy(PricingRule::class, PricingRulePolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower(trim(is_string($email) ? $email : '')).'|'.$request->ip()),
            );
        });
    }
}
