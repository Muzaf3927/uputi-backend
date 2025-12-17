<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Car extends Model
{
    protected $fillable = [
        'user_id',
        'model',
        'color',
        'number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
