<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomRolePermission extends Model
{
    public $timestamps = false;

    protected $fillable = ['custom_role_id', 'permission'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(CustomRole::class, 'custom_role_id');
    }
}
