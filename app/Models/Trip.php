<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_lat',
        'from_lng',
        'from_address',
        'to_lat',
        'to_lng',
        'to_address',
        'status',
        'role',
        'date',
        'time',
        'amount',
        'seats',
        'comment',
        'pochta',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
