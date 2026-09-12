<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'default_locale',
        'timezone',
        'currency',
        'is_active',
        'internal_identifier',
        'plan_reference',
        'status',
        'provisioning_key',
        'provisioning_payload_hash',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'lock_version' => 'integer'];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_owners')->withTimestamps();
    }
}
