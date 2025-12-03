<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_request_id',
        'user_id',
        'carModel',
        'carColor',
        'numberCar',
        'price',
        'status',
    ];

    public function passengerRequest()
    {
        return $this->belongsTo(PassengerRequest::class, 'passenger_request_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
