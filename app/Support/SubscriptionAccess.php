<?php

namespace App\Support;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;

/** The single request-time interpretation of subscription state and limits. */
final class SubscriptionAccess
{
    public const NORMAL = 'normal';

    public const GRACE = 'grace';

    public const RESTRICTED = 'restricted';

    /** @var list<string> */
    private const STATUSES = ['trialing', 'active', 'past_due', 'grace_period', 'suspended', 'cancelled', 'expired'];

    /** @param array<string, mixed> $limits @param array<string, mixed> $customLimits */
    private function __construct(
        private readonly string $status,
        private readonly ?CarbonImmutable $periodEndsAt,
        private readonly ?CarbonImmutable $trialEndsAt,
        private readonly ?CarbonImmutable $graceEndsAt,
        private readonly array $limits,
        private readonly array $customLimits,
        private readonly bool $hasSubscription,
        private readonly CarbonImmutable $now,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?DateTimeInterface $now = null): self
    {
        $now = $now ? CarbonImmutable::instance($now)->utc() : CarbonImmutable::now('UTC');
        $customLimitUntil = self::date($data['custom_limits_override_until'] ?? null);
        $customLimits = $customLimitUntil !== null && $customLimitUntil->lte($now)
            ? []
            : $data['custom_limits'] ?? [];

        return new self(
            strtolower(trim((string) ($data['status'] ?? 'missing'))),
            self::date($data['current_period_ends_at'] ?? null),
            self::date($data['trial_ends_at'] ?? null),
            self::date($data['grace_ends_at'] ?? null),
            self::limits($data['limits'] ?? []),
            self::limits($customLimits),
            array_key_exists('status', $data),
            $now,
        );
    }

    public static function forTenant(Tenant $tenant, ?DateTimeInterface $now = null): self
    {
        $tenant->loadMissing('currentSubscription.plan');
        $subscription = $tenant->getRelation('currentSubscription');
        if ($subscription === null) {
            return self::fromArray([], $now);
        }

        $nowValue = $now ? CarbonImmutable::instance($now)->utc() : CarbonImmutable::now('UTC');

        return self::fromArray([
            'status' => $subscription->getAttribute('status'),
            'current_period_ends_at' => $subscription->getAttribute('current_period_ends_at'),
            'trial_ends_at' => $subscription->getAttribute('trial_ends_at'),
            'grace_ends_at' => $subscription->getAttribute('grace_ends_at'),
            'limits' => $subscription->plan?->getAttribute('limits_json') ?? [],
            'custom_limits' => $subscription->getAttribute('custom_limits_json'),
            'custom_limits_override_until' => $subscription->getAttribute('custom_limits_override_until'),
        ], $nowValue);
    }

    public function status(): string
    {
        return $this->status;
    }

    /** Scheduler-independent lifecycle calculation; a missed tick remains safe. */
    public function phase(): string
    {
        // OQ-04 is an additive rollout: existing tenants without an explicit
        // commercial assignment stay operational until Platform provisions
        // their first subscription. Persisted unknown statuses still fail shut.
        if (! $this->hasSubscription) {
            return self::NORMAL;
        }
        if (! in_array($this->status, self::STATUSES, true)) {
            return self::RESTRICTED;
        }
        if (in_array($this->status, ['suspended', 'cancelled', 'expired'], true)) {
            return self::RESTRICTED;
        }

        $endsAt = $this->status === 'trialing' ? $this->trialEndsAt : $this->periodEndsAt;
        if ($endsAt === null) {
            return self::RESTRICTED;
        }
        if ($this->now->lt($endsAt) && $this->status !== 'grace_period') {
            return self::NORMAL;
        }

        // The approved seven days apply at request time as well as after sync.
        $graceEndsAt = $this->graceEndsAt ?? $endsAt->addDays(7);

        return $this->now->lt($graceEndsAt) ? self::GRACE : self::RESTRICTED;
    }

    public function isGrace(): bool
    {
        return $this->phase() === self::GRACE;
    }

    public function isRestricted(): bool
    {
        return $this->phase() === self::RESTRICTED;
    }

    /** Restricted tenants keep safe reads/account context and Owner reports. */
    public function allows(string $operation, bool $tenantOwner = false): bool
    {
        if (! $this->isRestricted()) {
            return true;
        }
        $operation = strtolower(trim($operation));
        if (in_array($operation, ['read', 'view', 'account', 'subscription'], true)) {
            return true;
        }

        return $tenantOwner && in_array($operation, ['report', 'reports', 'historical_report', 'historical_reports', 'report.view'], true);
    }

    /** @throws DomainException for a missing/malformed known plan limit. */
    public function limitFor(string $resource): ?int
    {
        $key = match (strtolower(trim($resource))) {
            'branch', 'branches' => 'branches',
            'user', 'users', 'staff' => 'users',
            default => throw new InvalidArgumentException("Unsupported subscription limit: {$resource}"),
        };
        if (! $this->hasSubscription) {
            return null;
        }
        if (array_key_exists($key, $this->customLimits)) {
            return self::limitValue($this->customLimits[$key], 'commercial override '.$key);
        }
        if (! array_key_exists($key, $this->limits)) {
            throw new DomainException("Subscribed plan is missing the {$key} limit.");
        }

        return self::limitValue($this->limits[$key], 'plan '.$key);
    }

    public function canCreate(string $resource, int $current, int $additional = 1): bool
    {
        if ($current < 0 || $additional < 0) {
            throw new InvalidArgumentException('Subscription counts must be non-negative.');
        }
        $limit = $this->limitFor($resource);

        return $limit === null || $current + $additional <= $limit;
    }

    /** @return array{phase: string, status: string, grace: bool, restricted: bool} */
    public function summary(): array
    {
        $phase = $this->phase();

        return ['phase' => $phase, 'status' => $this->status, 'grace' => $phase === self::GRACE, 'restricted' => $phase === self::RESTRICTED];
    }

    private static function limitValue(mixed $value, string $label): ?int
    {
        if ($value === null) {
            return null; // negotiated Enterprise/custom unlimited capacity
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw new DomainException("Malformed {$label} limit.");
        }

        return (int) $value;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof DateTimeInterface ? CarbonImmutable::instance($value)->utc() : CarbonImmutable::parse((string) $value, 'UTC')->utc();
    }

    /** @return array<string, mixed> */
    private static function limits(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
