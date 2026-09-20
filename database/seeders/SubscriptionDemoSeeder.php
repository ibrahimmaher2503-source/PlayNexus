<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class SubscriptionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('The synthetic subscription seed is limited to local and testing environments.');
        }

        DB::transaction(function (): void {
            $plans = [
                'starter' => ['name' => '[DEMO] Starter', 'limits' => ['branches' => 1, 'users' => 5], 'monthly' => 99000, 'yearly' => 990000],
                'growth' => ['name' => '[DEMO] Growth', 'limits' => ['branches' => 3, 'users' => 15], 'monthly' => 249000, 'yearly' => 2490000],
                'professional' => ['name' => '[DEMO] Professional', 'limits' => ['branches' => 10, 'users' => 50], 'monthly' => 599000, 'yearly' => 5990000],
                'enterprise' => ['name' => '[DEMO] Enterprise (custom quote)', 'limits' => ['branches' => null, 'users' => null], 'monthly' => null, 'yearly' => null],
            ];

            $planModels = [];
            foreach ($plans as $code => $planData) {
                $plan = Plan::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $planData['name'],
                        'description' => null,
                        'status' => 'active',
                        'limits_json' => $planData['limits'],
                        'features_json' => [],
                        'monthly_price_minor' => $planData['monthly'],
                        'annual_price_minor' => $planData['yearly'],
                        'annual_discount_bps' => 0,
                        'currency' => 'EGP',
                        'lock_version' => 1,
                    ],
                );
                $planModels[$code] = $plan;
            }

            foreach ([
                ['tenant' => 'demo-nile-alpha', 'plan' => 'growth', 'status' => 'trialing', 'reference' => 'demo-subscription-alpha'],
                ['tenant' => 'demo-nile-beta', 'plan' => 'starter', 'status' => 'active', 'reference' => 'demo-subscription-beta'],
            ] as $subscriptionData) {
                $tenant = Tenant::query()->where('internal_identifier', $subscriptionData['tenant'])->first();

                if (! $tenant) {
                    continue;
                }

                $plan = $planModels[$subscriptionData['plan']];
                $startsAt = now('UTC');
                $trialEndsAt = $subscriptionData['status'] === 'trialing' ? $startsAt->copy()->addDays(14) : null;
                $graceEndsAt = $subscriptionData['status'] === 'trialing' ? $startsAt->copy()->addDays(21) : null;

                $subscription = Subscription::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id],
                    [
                        'tenant_id' => $tenant->id,
                        'plan_id' => $plan->id,
                        'status' => $subscriptionData['status'],
                        'billing_interval' => 'monthly',
                        'price_amount_minor' => $plan->monthly_price_minor,
                        'price_currency' => $plan->currency,
                        'price_annual_discount_bps' => $plan->annual_discount_bps,
                        'custom_limits_json' => null,
                        'custom_limits_override_until' => null,
                        'custom_limits_reason' => null,
                        'custom_limits_set_by_user_id' => null,
                        'starts_at' => $startsAt,
                        'trial_ends_at' => $trialEndsAt,
                        'grace_ends_at' => $graceEndsAt,
                        'current_period_starts_at' => $startsAt,
                        'current_period_ends_at' => $startsAt->copy()->addMonth(),
                        'cancelled_at' => null,
                        'cancellation_reason' => null,
                        'lock_version' => 1,
                    ],
                );

                $tenant->forceFill([
                    'current_subscription_id' => $subscription->id,
                    'billing_status' => $subscriptionData['status'] === 'trialing' ? 'trial' : 'current',
                ])->save();
            }
        });
    }
}
