<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'branch_id', 'sequence_name', 'current_value', 'prefix', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'lock_version' => 'integer',
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
}
