<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    use HasApiTokens;
    protected $fillable = [
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
