<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Канал для конкретного трипа
Broadcast::channel('trip.{tripId}', function ($user, $tripId) {
    $trip = \App\Models\Trip::find($tripId);
    if (!$trip) {
        return false;
    }
    // Разрешаем доступ владельцу трипа и всем, кто забронировал этот трип
    return $trip->user_id === $user->id || 
           $trip->bookings()->where('user_id', $user->id)->exists();
});

// Канал для пользователя
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
