<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'tenant_id', 'branch_id', 'order_id', 'payment_id', 'requested_by_user_id',
        'approved_by_user_id', 'executed_by_user_id', 'amount_minor', 'currency', 'reason',
        'status', 'expected_order_lock_version', 'request_idempotency_key', 'request_fingerprint',
        'execution_idempotency_key', 'execution_fingerprint', 'requested_at', 'approved_at', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'expected_order_lock_version' => 'integer',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }
}
