<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaySessionEvent extends Model
{
    protected $fillable = [
        'tenant_id',
        'session_id',
        'event_type',
        'from_status',
        'to_status',
        'reason_code',
        'actor_user_id',
        'occurred_at',
        'metadata_json',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'metadata_json' => 'array',
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
