<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'ticket_type_id',
        'guardian_id',
        'child_id',
        'service_date',
        'status',
        'code_hash',
        'code_payload_encrypted',
        'display_code',
        'price_minor',
        'currency',
        'price_snapshot_json',
        'issued_at',
        'valid_from',
        'valid_until',
        'assignment_locked_at',
        'consumed_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'uses_count',
        'max_uses',
        'idempotency_key',
        'issue_fingerprint',
        'issued_by_user_id',
        'lock_version',
    ];

    protected $hidden = ['code_hash', 'code_payload_encrypted', 'issue_fingerprint', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'service_date' => 'date:Y-m-d',
            'code_payload_encrypted' => 'encrypted',
            'price_minor' => 'integer',
            'price_snapshot_json' => 'array',
            'issued_at' => 'immutable_datetime',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'assignment_locked_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'uses_count' => 'integer',
            'max_uses' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(TicketScan::class);
    }

    public function playSession(): HasOne
    {
        return $this->hasOne(PlaySession::class);
    }
}
