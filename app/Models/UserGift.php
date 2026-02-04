<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGift extends Model
{
    protected $table = 'user_gifts';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'integer',
    ];

    /**
     * Пользователь, которому выдан gift
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Хелпер: проверить, был ли уже выдан gift
     */
    public static function alreadyGiven(int $userId, string $type): bool
    {
        return self::where('user_id', $userId)
            ->where('type', $type)
            ->exists();
    }
}
