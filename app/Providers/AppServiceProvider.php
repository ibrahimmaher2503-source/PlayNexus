<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Child;
use App\Models\CustomRole;
use App\Models\Guardian;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\GuardianPolicy;
use App\Policies\PosPolicy;
use App\Policies\PricingRulePolicy;
use App\Policies\TenantPolicy;
use App\Services\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        Gate::policy(Product::class, PosPolicy::class);

        foreach ([
            'user' => User::class,
            'branch' => Branch::class,
            'guardian' => Guardian::class,
            'relatedGuardian' => Guardian::class,
            'child' => Child::class,
            'pricingRule' => PricingRule::class,
            'role' => CustomRole::class,
            'session' => PlaySession::class,
            'ticket' => Ticket::class,
        ] as $parameter => $model) {
            Route::bind($parameter, function (mixed $value) use ($model): object {
                $tenant = app(TenantContext::class)->current();

                abort_unless($tenant, 404);

                $query = $model::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->whereKey($value);

                if ($model === PlaySession::class) {
                    $user = request()->user();
                    abort_unless($user instanceof User, 404);

                    $query->whereIn('branch_id', $user->accessibleBranches()->select('branches.id'));
                }

                return $query->firstOrFail();
            });
        }

        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower(trim(is_string($email) ? $email : '')).'|'.$request->ip()),
            );
        });
        RateLimiter::for('mfa', fn (Request $request) => Limit::perMinute(5)->by(
            'mfa|'.($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip(),
        ));
    }
}
