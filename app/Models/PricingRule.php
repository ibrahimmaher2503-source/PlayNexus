<?php

namespace App\Models;

use Database\Factories\PricingRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    /** @use HasFactory<PricingRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'code',
        'name',
        'version',
        'billing_mode',
        'base_duration_seconds',
        'base_price_minor',
        'grace_period_seconds',
        'overtime_unit_seconds',
        'overtime_price_minor',
        'currency',
        'tax_rate_bps',
        'tax_mode',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'base_duration_seconds' => 'integer',
            'base_price_minor' => 'integer',
            'grace_period_seconds' => 'integer',
            'overtime_unit_seconds' => 'integer',
            'overtime_price_minor' => 'integer',
            'tax_rate_bps' => 'integer',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
