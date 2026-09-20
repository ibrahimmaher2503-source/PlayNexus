<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketScan extends Model
{
    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'branch_id',
        'scanned_by_user_id',
        'scanned_at',
        'scan_purpose',
        'result',
        'code_hash',
        'idempotency_key',
        'request_fingerprint',
        'request_id',
    ];

    protected $hidden = ['code_hash', 'idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['scanned_at' => 'immutable_datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
