<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'from_city', 'to_city', 'date', 'time',
        'seats', 'price', 'note', 'carModel', 'carColor', 'status',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function confirmedBookings()
    {
        return $this->hasMany(Booking::class)->where('status', 'confirmed');
    }

    public function getBookedSeatsAttribute()
    {
        return $this->confirmedBookings()->sum('seats');
    }

    public function getAvailableSeatsAttribute()
    {
        return $this->seats - $this->booked_seats;
    }
}
