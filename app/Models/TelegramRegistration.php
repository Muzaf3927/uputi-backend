<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramRegistration extends Model
{
    protected $fillable = [
        'chat_id',
        'phone',
        'name',
        'step',
    ];
}
