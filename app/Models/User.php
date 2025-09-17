<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'phone',
        'role',
        'password',
        'balance',
        'rating',
        'rating_count',
        'is_verified',
        'verification_code',
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
    ];


    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
    public function ratingsGiven()
    {
        return $this->hasMany(Rating::class, 'from_user_id');
    }
    public function ratingsReceived()
    {
        return $this->hasMany(Rating::class, 'to_user_id');
    }
    public function sentMessages()
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }
    public function receivedMessages()
    {
        return $this->hasMany(ChatMessage::class, 'receiver_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('is_read', false);
    }



}
