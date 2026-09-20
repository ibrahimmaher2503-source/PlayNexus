<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignSubscriptionRequest;
use App\Http\Requests\ExtendTrialRequest;
use App\Http\Requests\StoreSubscriptionInvoiceRequest;
use App\Http\Requests\SubscriptionLimitsRequest;
use App\Http\Requests\SubscriptionStatusRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionBillingRecord;
use App\Models\Tenant;
use App\Support\SubscriptionAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlatformSubscriptionController extends Controller
{
    private const STATUSES = ['trialing', 'active', 'past_due', 'grace_period', 'suspended', 'cancelled', 'expired'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $subscriptions = Subscription::query()
            ->with(['tenant' => fn ($query) => $query->withCount(['branches as branch_usage', 'users as user_usage']), 'plan'])
            ->when($status !== '' && in_array($status, self::STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $query) => $query
                    ->whereHas('tenant', fn (Builder $tenant) => $tenant->where('name', 'like', "%{$search}%")->orWhere('internal_identifier', 'like', "%{$search}%"))
                    ->orWhereHas('plan', fn (Builder $plan) => $plan->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")));
            })
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(25)->withQueryString();

        $summaries = [];
        foreach ($subscriptions as $subscription) {
            $access = SubscriptionAccess::fromArray([
                'status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_ends_at,
                'grace_ends_at' => $subscription->grace_ends_at,
                'current_period_ends_at' => $subscription->current_period_ends_at,
                'limits' => $subscription->plan?->limits_json ?? [],
                'custom_limits' => $subscription->custom_limits_json,
                'custom_limits_override_until' => $subscription->custom_limits_override_until,
            ]);
            $summaries[$subscription->getKey()] = [
                'branch_limit' => $access->limitFor('branches'),
                'user_limit' => $access->limitFor('users'),
                'branch_usage' => (int) ($subscription->tenant?->branch_usage ?? 0),
                'user_usage' => (int) ($subscription->tenant?->user_usage ?? 0),
                'phase' => $access->phase(),
            ];
        }

        $tenantIds = $subscriptions->getCollection()->pluck('tenant_id')->unique()->values();

        return view('platform.subscriptions.index', [
            'plans' => Plan::query()->where('status', 'active')->orderBy('name')->get(),
            'tenants' => Tenant::query()->with(['currentSubscription.plan'])->orderBy('name')->orderBy('id')->get(),
            'subscriptions' => $subscriptions,
            'summaries' => $summaries,
            'invoices' => SubscriptionBillingRecord::query()->with(['tenant', 'subscription', 'recordedBy'])->orderByDesc('paid_at')->orderByDesc('id')->limit(50)->get(),
            'audits' => DB::table('platform_audit_logs')
                ->whereIn('target_tenant_id', $tenantIds)
                ->where('action', 'like', 'subscription.%')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->groupBy('subject_id'),
            'filters' => ['q' => $search, 'status' => $status],
        ]);
    }

    public function assign(AssignSubscriptionRequest $request, Tenant $tenant): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $subscription = DB::transaction(function () use ($actor, $data, $tenant): Subscription {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $expectedCurrent = $data['expected_current_subscription_id'] ?? null;
            if ((string) ($lockedTenant->current_subscription_id ?? '') !== (string) ($expectedCurrent ?? '')) {
                throw new HttpException(409, __('platform.subscriptions.conflict'));
            }
            $plan = Plan::query()->whereKey($data['plan_id'])->where('status', 'active')->lockForUpdate()->firstOrFail();
            $startsAt = $this->utc($data['starts_at'] ?? now('UTC')->toDateTimeString());
            $status = $data['status'] ?? 'trialing';
            $trialEndsAt = $status === 'trialing' ? $this->utc($data['trial_ends_at'] ?? $startsAt->addDays(14)->toDateTimeString()) : null;
            $periodStartsAt = $this->utc($data['current_period_starts_at'] ?? $startsAt->toDateTimeString());
            $periodEndsAt = $this->utc($data['current_period_ends_at'] ?? $this->periodEnd($periodStartsAt, $data['billing_interval'])->toDateTimeString());
            $limits = $this->limits($data);

            $subscription = new Subscription;
            $subscription->forceFill([
                'tenant_id' => $lockedTenant->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => $status,
                'billing_interval' => $data['billing_interval'],
                'price_amount_minor' => $this->priceFor($plan, $data['billing_interval']),
                'price_currency' => 'EGP',
                'price_annual_discount_bps' => (int) $plan->getAttribute('annual_discount_bps'),
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => $trialEndsAt?->addDays(7),
                'current_period_starts_at' => $periodStartsAt,
                'current_period_ends_at' => $periodEndsAt,
                'custom_limits_json' => $limits,
                'custom_limits_override_until' => $this->overrideUntil($data),
                'custom_limits_reason' => $limits ? $data['reason'] : null,
                'custom_limits_set_by_user_id' => $limits ? $actor->getKey() : null,
            ])->save();

            $before = $lockedTenant->current_subscription_id ? ['current_subscription_id' => $lockedTenant->current_subscription_id] : null;
            $lockedTenant->forceFill(['current_subscription_id' => $subscription->getKey(), 'plan_reference' => $plan->code, 'billing_status' => $this->billingStatus($status)])->save();
            $this->audit($actor->getKey(), 'subscription.assigned', $subscription, $before, $this->snapshot($subscription), $data['reason']);

            return $subscription->load(['tenant', 'plan']);
        });

        return $this->respond($request, $subscription, 'platform.subscriptions.assigned');
    }

    public function updateStatus(SubscriptionStatusRequest $request, Subscription $subscription): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        [$updated, $changed] = DB::transaction(function () use ($actor, $data, $subscription): array {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureCurrent($locked);
            if ((string) $locked->status !== $data['expected_status']) {
                throw new HttpException(409, __('platform.subscriptions.conflict'));
            }
            if ((string) $locked->status === $data['status']) {
                return [$locked, false];
            }
            if (! $locked->canTransitionTo($data['status'])) {
                throw new HttpException(422, __('platform.subscriptions.invalid_transition'));
            }
            $before = $this->snapshot($locked);
            $locked->forceFill(['status' => $data['status'], 'cancelled_at' => $data['status'] === 'cancelled' ? now('UTC') : null, 'cancellation_reason' => $data['status'] === 'cancelled' ? $data['reason'] : null])->save();
            $this->updateTenantBillingStatus($locked);
            $this->audit($actor->getKey(), 'subscription.status.changed', $locked, $before, $this->snapshot($locked), $data['reason']);

            return [$locked, true];
        });

        return $request->expectsJson() ? response()->json(['data' => $this->snapshot($updated), 'changed' => $changed]) : to_route('platform.subscriptions.index')->with('success', __($changed ? 'platform.subscriptions.status_updated' : 'platform.subscriptions.no_change'));
    }

    public function extendTrial(ExtendTrialRequest $request, Subscription $subscription): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $updated = DB::transaction(function () use ($actor, $data, $subscription): Subscription {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureCurrent($locked);
            if (! $locked->canTransitionTo('trialing') && $locked->status !== 'trialing') {
                throw new HttpException(422, __('platform.subscriptions.invalid_transition'));
            }
            $before = $this->snapshot($locked);
            $trialEnd = $this->utc($data['trial_ends_at']);
            $locked->forceFill(['status' => 'trialing', 'trial_ends_at' => $trialEnd, 'grace_ends_at' => $trialEnd->addDays(7), 'cancelled_at' => null, 'cancellation_reason' => null])->save();
            $this->updateTenantBillingStatus($locked);
            $this->audit($actor->getKey(), 'subscription.trial.extended', $locked, $before, $this->snapshot($locked), $data['reason']);

            return $locked;
        });

        return $this->respond($request, $updated, 'platform.subscriptions.trial_extended');
    }

    public function updateLimits(SubscriptionLimitsRequest $request, Subscription $subscription): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $updated = DB::transaction(function () use ($actor, $data, $subscription): Subscription {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureCurrent($locked);
            $before = $this->snapshot($locked);
            $limits = $this->limits($data);
            $locked->forceFill(['custom_limits_json' => $limits, 'custom_limits_override_until' => $this->overrideUntil($data), 'custom_limits_reason' => $limits ? $data['reason'] : null, 'custom_limits_set_by_user_id' => $limits ? $actor->getKey() : null])->save();
            $this->audit($actor->getKey(), $limits ? 'subscription.limits.overridden' : 'subscription.limits.revoked', $locked, $before, $this->snapshot($locked), $data['reason']);

            return $locked;
        });

        return $this->respond($request, $updated, 'platform.subscriptions.limits_updated');
    }

    public function storeInvoice(StoreSubscriptionInvoiceRequest $request, Subscription $subscription): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $invoice = DB::transaction(function () use ($actor, $data, $subscription): SubscriptionBillingRecord {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $invoice = new SubscriptionBillingRecord;
            $invoice->forceFill(['tenant_id' => $locked->tenant_id, 'subscription_id' => $locked->getKey(), 'reference' => $data['reference'], 'amount_minor' => (int) $data['amount_minor'], 'currency' => 'EGP', 'billing_period_starts_at' => $this->utc($data['billing_period_start']), 'billing_period_ends_at' => $this->utc($data['billing_period_end']), 'paid_at' => $this->utc($data['payment_date']), 'payment_method' => $data['payment_method'], 'notes' => $data['notes'] ?? null, 'recorded_by_user_id' => $actor->getKey()])->save();
            $this->audit($actor->getKey(), 'subscription.invoice.recorded', $invoice, null, $this->snapshot($invoice), $data['reason']);

            return $invoice;
        });

        return $this->respond($request, $invoice, 'platform.subscriptions.invoice_recorded', 201);
    }

    private function priceFor(Plan $plan, string $interval): int
    {
        return (int) $plan->getAttribute($interval === 'yearly' ? 'annual_price_minor' : 'monthly_price_minor');
    }

    private function updateTenantBillingStatus(Subscription $subscription): void
    {
        Tenant::query()->whereKey($subscription->tenant_id)->where('current_subscription_id', $subscription->getKey())->update(['billing_status' => $this->billingStatus((string) $subscription->status)]);
    }

    private function ensureCurrent(Subscription $subscription): void
    {
        $currentId = Tenant::query()->whereKey($subscription->tenant_id)->lockForUpdate()->value('current_subscription_id');
        if ((int) $currentId !== (int) $subscription->getKey()) {
            throw new HttpException(409, __('platform.subscriptions.conflict'));
        }
    }

    private function periodEnd(CarbonImmutable $start, string $interval): CarbonImmutable
    {
        return $interval === 'yearly' ? $start->addYear() : $start->addMonth();
    }

    private function utc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'UTC')->utc();
    }

    private function billingStatus(string $status): string
    {
        return match ($status) {
            'trialing' => 'trial', 'active' => 'current', default => $status
        };
    }

    /** @return array<string, int>|null */
    private function limits(array $data): ?array
    {
        $limits = $data['custom_limits'] ?? null;

        return is_array($limits) && array_filter($limits, static fn ($value): bool => $value !== null && $value !== '') !== [] ? array_filter($limits, static fn ($value): bool => $value !== null && $value !== '') : null;
    }

    private function overrideUntil(array $data): ?CarbonImmutable
    {
        return isset($data['override_until']) && $data['override_until'] !== '' ? $this->utc($data['override_until']) : null;
    }

    private function respond(Request $request, object $resource, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['data' => $resource->toArray()], $status) : to_route('platform.subscriptions.index')->with('success', __($message));
    }

    /** @return array<string, mixed> */
    private function snapshot(object $resource): array
    {
        $keys = $resource instanceof Subscription ? ['id', 'tenant_id', 'plan_id', 'status', 'billing_interval', 'price_amount_minor', 'price_currency', 'price_annual_discount_bps', 'starts_at', 'trial_ends_at', 'grace_ends_at', 'current_period_starts_at', 'current_period_ends_at', 'cancelled_at', 'cancellation_reason', 'custom_limits_json', 'custom_limits_override_until'] : ['id', 'tenant_id', 'subscription_id', 'reference', 'amount_minor', 'currency', 'billing_period_starts_at', 'billing_period_ends_at', 'payment_method', 'paid_at'];

        return collect($keys)->mapWithKeys(static fn (string $key): array => [$key => $resource->getAttribute($key)])->all();
    }

    private function audit(int $actorId, string $action, object $resource, ?array $before, array $after, string $reason): void
    {
        DB::table('platform_audit_logs')->insert(['actor_user_id' => $actorId, 'target_tenant_id' => $resource->getAttribute('tenant_id'), 'action' => $action, 'subject_type' => $resource instanceof Subscription ? 'subscription' : 'subscription_billing_record', 'subject_id' => (string) $resource->getKey(), 'outcome' => 'success', 'reason_code' => $reason, 'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'request_id' => (string) Str::uuid(), 'occurred_at' => now('UTC')]);
    }
}
