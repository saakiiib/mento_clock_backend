<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class PlatformAdmin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'active'];
    protected $hidden = ['password', 'remember_token'];
    protected $attributes = ['active' => true, 'auth_version' => 1];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'auth_version' => 'integer'];
    }
}
