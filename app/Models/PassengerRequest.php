<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerRequest extends Model
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
        'date',
        'time',
        'amount',
        'seats',
        'comment',
        'status',
    ];

    public function passenger()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driverOffers()
    {
        return $this->hasMany(DriverOffer::class, 'passenger_request_id');
    }
}
