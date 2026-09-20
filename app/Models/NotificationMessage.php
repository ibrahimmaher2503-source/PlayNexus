<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationMessage extends Model
{
    protected $fillable = [
        'tenant_id', 'branch_id', 'guardian_id', 'session_id', 'order_id', 'channel', 'purpose',
        'template_key', 'template_version', 'locale', 'destination_encrypted', 'destination_masked',
        'payload_json', 'status', 'dedupe_key', 'scheduled_at', 'sent_at', 'delivered_at',
        'failed_at', 'stale_at', 'provider_message_id', 'attempt_count', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'destination_encrypted' => 'encrypted',
            'payload_json' => 'array',
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'stale_at' => 'immutable_datetime',
            'attempt_count' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
