<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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



}
