<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id', 'user_id', 'seats', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);

    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

