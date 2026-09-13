<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaySession extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'child_id',
        'guardian_id',
        'ticket_id',
        'pricing_rule_id',
        'status',
        'started_at',
        'expected_end_at',
        'ended_at',
        'pricing_snapshot_json',
        'created_by_user_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'expected_end_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'pricing_snapshot_json' => 'array',
            'lock_version' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlaySessionEvent::class, 'session_id');
    }
}
