<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['tenant_id', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot(['tenant_id', 'role', 'is_active'])->withTimestamps();
    }

    public function activeBranches(): BelongsToMany
    {
        return $this->branches()
            ->where('branches.tenant_id', $this->tenant_id)
            ->where('branches.is_active', true)
            ->wherePivot('tenant_id', $this->tenant_id)
            ->wherePivot('is_active', true);
    }

    public function accessibleBranches(): Builder
    {
        $userId = $this->getAuthIdentifier();

        return Branch::query()
            ->where('branches.is_active', true)
            ->whereExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('tenants')
                    ->whereColumn('tenants.id', 'branches.tenant_id')
                    ->where('tenants.is_active', true);
            })
            ->where(function (Builder $query) use ($userId): void {
                $query->whereExists(function (QueryBuilder $query) use ($userId): void {
                    $query->selectRaw('1')
                        ->from('tenant_owners')
                        ->join('users', 'users.id', '=', 'tenant_owners.user_id')
                        ->whereColumn('tenant_owners.tenant_id', 'branches.tenant_id')
                        ->whereColumn('users.tenant_id', 'branches.tenant_id')
                        ->where('tenant_owners.user_id', $userId)
                        ->where('users.status', 'active');
                })->orWhereExists(function (QueryBuilder $query) use ($userId): void {
                    $query->selectRaw('1')
                        ->from('branch_user')
                        ->join('users', 'users.id', '=', 'branch_user.user_id')
                        ->whereColumn('branch_user.branch_id', 'branches.id')
                        ->whereColumn('branch_user.tenant_id', 'branches.tenant_id')
                        ->whereColumn('users.tenant_id', 'branches.tenant_id')
                        ->where('branch_user.user_id', $userId)
                        ->where('branch_user.is_active', true)
                        ->where('users.status', 'active');
                });
            });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
