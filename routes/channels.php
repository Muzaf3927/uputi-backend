<?php

use Illuminate\Support\Facades\Broadcast;

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
