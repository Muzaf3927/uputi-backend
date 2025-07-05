<?php

use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SettingController;

Route::post('/register', [AuthController::class, 'register']); // создать пароль
Route::post('/login', [AuthController::class, 'login']); //зайти
Route::post('/reset-password', [AuthController::class, 'resetPassword']); //обновить пароль

// Защищённые маршруты
Route::middleware('auth:sanctum')->group(function () {
    //Trips - Поездки
    Route::post('/trip', [TripController::class, 'store']); // создать
    Route::get('/my-trips', [TripController::class, 'myTrips']); //мои поездки
    Route::get('/trips', [TripController::class, 'index']); //все поездки

    //Bookings - Броны
    Route::post('/trips/{trip}/booking', [BookingController::class, 'store']); //бронировать
    Route::patch('/bookings/{booking}', [BookingController::class, 'update']); //бронировать

    //Messages - Чаты
    Route::post('/chats/{trip}/send', [ChatController::class, 'sendMessage']); //отправить сообщение
    Route::get('/chats/{trip}/with/{receiver}', [ChatController::class, 'getChatMessages']); //получить чат

    //Wallets - Кошелек
    Route::get('/wallet', [WalletController::class, 'getWallet']); //баланс
    Route::post('/wallet/deposit', [WalletController::class, 'deposit']);  //пополнить
    Route::get('/wallet/transactions', [WalletController::class, 'getTransactions']); //история транзакции

    //Ratings - Оценка
    Route::post('/ratings/{trip}/to/{toUser}', [RatingController::class, 'rateUser']);  //поставить оценку
    Route::get('/ratings/user/{user}', [RatingController::class, 'getUserRatings']);  //отзывы пользователя
    Route::get('/ratings/given', [RatingController::class, 'getMyRatingsGiven']); //мои отзывы

    //Settings - Настройка
    Route::get('/settings', [SettingController::class, 'index']); //получить настройки
    Route::get('/settings/{key}', [SettingController::class, 'show']);  //получить одну настройку
    Route::post('/settings', [SettingController::class, 'store']); //создать или обновить
    Route::delete('/settings/{key}', [SettingController::class, 'destroy']); //удалить



    Route::post('/logout', [AuthController::class, 'logout']); //выход

});
