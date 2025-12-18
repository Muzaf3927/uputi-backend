<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Private user channel
|--------------------------------------------------------------------------
| Используется для:
| - TripBooked
| - TripUpdated
|
| Канал: user.{id}
| Доступ ТОЛЬКО владельцу user.id
*/
Broadcast::channel('user.{id}', function ($user, $id) {
    Log::info('Broadcast channel authorization', [
        'channel' => 'user.' . $id,
        'user_id' => $user->id,
        'requested_id' => $id,
        'authorized' => (int) $user->id === (int) $id,
    ]);
    
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Public drivers trips channel
|--------------------------------------------------------------------------
| Используется для:
| - TripCreated
|
| Публичный канал, авторизация не нужна
*/
Broadcast::channel('drivers.trips', function () {
    return true;
});
