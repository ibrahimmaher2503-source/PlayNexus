<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaySessionCommand extends Model
{
    protected $fillable = [
        'tenant_id',
        'session_id',
        'actor_user_id',
        'idempotency_key',
        'command',
        'fingerprint',
        'result_status',
        'result_lock_version',
        'response_json',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'executed_at' => 'immutable_datetime',
            'result_lock_version' => 'integer',
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
