<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'limits_json',
        'features_json',
        'monthly_price_minor',
        'annual_price_minor',
        'annual_discount_bps',
        'currency',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'limits_json' => 'array',
            'features_json' => 'array',
            'monthly_price_minor' => 'integer',
            'annual_price_minor' => 'integer',
            'annual_discount_bps' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
