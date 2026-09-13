<?php

namespace App\Models;

use Database\Factories\ChildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'full_name',
        'date_of_birth',
        'emergency_contact_name',
        'emergency_contact_phone_e164',
        'safety_notes_encrypted',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'safety_notes_encrypted' => 'encrypted',
            'lock_version' => 'integer',
        ];
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

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_child')
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

    public function playSessions(): HasMany
    {
        return $this->hasMany(PlaySession::class);
    }
}
