<?php

namespace App\Models;

use Database\Factories\SubscriptionBillingRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionBillingRecord extends Model
{
    /** @use HasFactory<SubscriptionBillingRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'reference',
        'payment_method',
        'amount_minor',
        'currency',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'paid_at',
        'notes',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'billing_period_starts_at' => 'immutable_datetime',
            'billing_period_ends_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
