<?php

namespace App\Models;

use App\Support\PhoneNormalizer;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'full_name',
        'phone_e164',
        'email',
        'preferred_locale',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'guardian_child')
            ->withPivot([
                'tenant_id',
                'relationship_type',
                'can_consent',
                'can_check_out',
                'is_primary',
                'verification_method',
                'verified_at',
                'verified_by_user_id',
                'is_active',
                'revoked_at',
                'revoked_by_user_id',
                'created_by_user_id',
                'updated_by_user_id',
            ])
            ->withTimestamps();
    }

    public function maskedPhone(): string
    {
        return PhoneNormalizer::mask($this->phone_e164);
    }

    public function playSessions(): HasMany
    {
        return $this->hasMany(PlaySession::class);
    }
}
