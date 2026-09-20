<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_interval',
        'price_amount_minor',
        'price_currency',
        'price_annual_discount_bps',
        'custom_limits_json',
        'custom_limits_override_until',
        'custom_limits_reason',
        'custom_limits_set_by_user_id',
        'starts_at',
        'trial_ends_at',
        'grace_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'cancelled_at',
        'cancellation_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'price_amount_minor' => 'integer',
            'price_annual_discount_bps' => 'integer',
            'custom_limits_json' => 'array',
            'custom_limits_override_until' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'current_period_starts_at' => 'immutable_datetime',
            'current_period_ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customLimitsSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custom_limits_set_by_user_id');
    }

    public function billingRecords(): HasMany
    {
        return $this->hasMany(SubscriptionBillingRecord::class);
    }

    /**
     * Explicit application-level lifecycle policy. The platform action must
     * lock the subscription before using this, so stale status writes conflict.
     */
    public function canTransitionTo(string $target): bool
    {
        $current = (string) $this->getAttribute('status');

        if ($current === $target) {
            return true;
        }

        return in_array($target, match ($current) {
            'trialing' => ['active', 'grace_period', 'suspended', 'cancelled', 'expired'],
            'active' => ['past_due', 'grace_period', 'suspended', 'cancelled', 'expired'],
            'past_due' => ['active', 'grace_period', 'suspended', 'cancelled', 'expired'],
            'grace_period' => ['active', 'suspended', 'cancelled', 'expired'],
            'suspended', 'cancelled', 'expired' => ['active'],
            default => [],
        }, true);
    }
}
