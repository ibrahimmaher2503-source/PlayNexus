<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'lock_version',
    ];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(CustomRolePermission::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('permission', $permission)->exists();
    }

    /** @return array{name: string, code: string, lock_version: int, permissions: list<string>} */
    public function snapshot(): array
    {
        return [
            'name' => (string) $this->name,
            'code' => (string) $this->code,
            'lock_version' => (int) $this->lock_version,
            'permissions' => $this->permissions()->pluck('permission')->sort()->values()->all(),
        ];
    }
}
