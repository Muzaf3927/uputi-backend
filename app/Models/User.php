<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'role',
        'password',
        'balance',
        'rating',
        'rating_count',
        'telegram_chat_id'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'rating' => 'float',
        'balance' => 'integer',
        'rating_count' => 'integer',
        'deleted_at' => 'datetime',
    ];


    public function car()
    {
        return $this->hasOne(Car::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }




}
