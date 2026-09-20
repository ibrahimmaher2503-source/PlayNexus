<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'branch_id', 'order_id', 'requested_by_user_id', 'approved_by_user_id',
        'rejected_by_user_id', 'consumed_by_user_id', 'discount_minor', 'currency', 'reason',
        'rejection_reason', 'payload_fingerprint', 'expected_order_lock_version', 'status',
        'request_payload_json',
        'expires_at', 'approved_at', 'rejected_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'request_payload_json' => 'array',
            'expected_order_lock_version' => 'integer',
            'expires_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_user_id');
    }
}
