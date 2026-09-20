<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'notification_message_id', 'attempt_number', 'provider', 'started_at', 'finished_at',
        'outcome', 'provider_status_code', 'provider_message_id', 'error_code', 'error_summary_masked',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'attempt_number' => 'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(NotificationMessage::class, 'notification_message_id');
    }
}
