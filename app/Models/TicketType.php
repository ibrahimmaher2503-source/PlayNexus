<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'pricing_rule_id',
        'code',
        'name',
        'price_minor',
        'currency',
        'max_uses',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'max_uses' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
