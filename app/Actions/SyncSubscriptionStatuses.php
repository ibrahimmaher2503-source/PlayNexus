<?php

namespace App\Actions;

use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncSubscriptionStatuses
{
    /** @return array{status_changes: int, expired_overrides: int} */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $result = ['status_changes' => 0, 'expired_overrides' => 0];

        Subscription::query()->select('id')->orderBy('id')->chunkById(100, function ($subscriptions) use (&$result, $now): void {
            foreach ($subscriptions as $subscription) {
                DB::transaction(function () use ($subscription, &$result, $now): void {
                    $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
                    $next = $this->nextStatus($locked, $now);
                    if ($next !== null && $next !== $locked->status) {
                        $before = $this->snapshot($locked);
                        $changes = ['status' => $next];
                        if ($next === 'grace_period' && $locked->grace_ends_at === null) {
                            $periodEnd = $locked->status === 'trialing'
                                ? $locked->trial_ends_at
                                : $locked->current_period_ends_at;
                            $changes['grace_ends_at'] = $periodEnd?->addDays(7);
                        }
                        $locked->forceFill($changes)->save();
                        $this->audit($locked, 'subscription.status.synced', $before, $this->snapshot($locked), 'lifecycle_sync', $now);
                        DB::table('tenants')->where('id', $locked->tenant_id)
                            ->where('current_subscription_id', $locked->getKey())
                            ->update(['billing_status' => $this->tenantBillingStatus($next), 'updated_at' => $now]);
                        $result['status_changes']++;
                    }

                    $until = $locked->custom_limits_override_until;
                    if ($until !== null && $until->lte($now)) {
                        $before = $this->snapshot($locked);
                        $locked->forceFill([
                            'custom_limits_json' => null,
                            'custom_limits_override_until' => null,
                            'custom_limits_reason' => null,
                            'custom_limits_set_by_user_id' => null,
                        ])->save();
                        $this->audit($locked, 'subscription.commercial_override.expired', $before, $this->snapshot($locked), 'override_expired', $now);
                        $result['expired_overrides']++;
                    }
                });
            }
        });

        return $result;
    }

    private function tenantBillingStatus(string $status): string
    {
        return match ($status) {
            'trialing' => 'trial',
            'active' => 'current',
            default => $status,
        };
    }

    private function nextStatus(Subscription $subscription, CarbonImmutable $now): ?string
    {
        $status = (string) $subscription->status;
        if (! in_array($status, ['trialing', 'active', 'past_due', 'grace_period'], true)) {
            return null;
        }

        $periodEnd = $status === 'trialing' ? $subscription->trial_ends_at : $subscription->current_period_ends_at;
        if ($periodEnd === null) {
            return null;
        }
        $graceEnd = $subscription->grace_ends_at ?? $periodEnd->addDays(7);

        if ($now->gte($graceEnd)) {
            return 'expired';
        }
        if ($status !== 'grace_period' && $now->gte($periodEnd)) {
            return 'grace_period';
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function snapshot(Subscription $subscription): array
    {
        return [
            'id' => $subscription->getKey(), 'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at, 'grace_ends_at' => $subscription->grace_ends_at,
            'current_period_ends_at' => $subscription->current_period_ends_at,
            'custom_limits_json' => $subscription->custom_limits_json,
            'custom_limits_override_until' => $subscription->custom_limits_override_until,
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function audit(Subscription $subscription, string $action, array $before, array $after, string $reason, CarbonImmutable $now): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_user_id' => null,
            'actor_type' => 'system',
            'target_tenant_id' => $subscription->tenant_id,
            'action' => $action,
            'subject_type' => 'subscription',
            'subject_id' => (string) $subscription->getKey(),
            'outcome' => 'success',
            'reason_code' => $reason,
            'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
        ]);
    }
}
