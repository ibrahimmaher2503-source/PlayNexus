<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaySessionAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'session_id',
        'actor_user_id',
        'extension_units',
        'adjustment_minor',
        'reason',
        'expected_lock_version',
        'applied_lock_version',
        'request_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'extension_units' => 'integer',
            'adjustment_minor' => 'integer',
            'expected_lock_version' => 'integer',
            'applied_lock_version' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
