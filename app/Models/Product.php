<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'branch_id', 'sku', 'name', 'type', 'price_minor', 'currency',
        'tax_rate_bps', 'tax_mode', 'status', 'archived_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'archived_at' => 'immutable_datetime',
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

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
