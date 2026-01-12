<?php

use App\Http\Controllers\CarController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\TelegramConnectController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\AddressController;

Route::get('/test', function () {
    return 'test';
});


Route::post('count/download', [DownloadController::class, 'store']);

Route::post('/auth/start', [AuthController::class, 'start'])->middleware('sms.throttle');
Route::post('/auth/verify', [AuthController::class, 'verify']);

//удаление отделный
Route::post('/account/delete/send-otp', [AccountDeletionController::class, 'sendOtp']);
Route::post('/account/delete/verify', [AccountDeletionController::class, 'verifyAndDelete']);

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);


// Защищённые маршруты
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/telegram/connect', [TelegramConnectController::class, 'connect']);

    Route::post('/geocode/reverse', [AddressController::class, 'addressReverse']);

    Route::get('/user', [UserController::class, 'me']); //Получения данных пользователя
    Route::post('/user', [UserController::class, 'update']);
    Route::post('/role/update', [UserController::class, 'updateRole']);

//Trips - Поездки
    // 1. Создать поездку (и пассажир, и водитель)
    Route::post('/trips', [TripController::class, 'store']); //ок
    // мои поездки для водителей кроме завершенных
    Route::get('/trips/my', [TripController::class, 'myTrips']); //ок
    // мои поездки для пассажиров кроме завершенных
    Route::get('/trips/for/passenger/my', [TripController::class, 'myTripsForPassenger']);

    // 5. Все активные поездки для водителй (по городу)
    Route::get('/trips/active', [TripController::class, 'activeTrips']); //ок
    // 5. Все активные поездки для пассажиров (межгород)
    Route::get('/trips/for/passenger/active', [TripController::class, 'activeTripsForPassengers']);
    // 6. завершить поездку пассажиров для водителей
    Route::put('/trips/{trip}/completed', [TripController::class, 'completed']);
     // 6. завершить свою поездку для водителей
    Route::put('/trips/{trip}/completedIntercity', [TripController::class, 'completedIntercity']);
    // 7. Удалить поездку
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']); //ок
    // 8. Искать поездку
    Route::get('/trips/search', [TripController::class, 'search']);
    // 8. Искать пассажира
    Route::get('/trips/search/passengers/for/driver', [TripController::class, 'searchPassengerOrders']);


//Bookings - Броны
    // 1. Забронировать заказ для водителей
    Route::post('/bookings', [BookingController::class, 'store']);
    // 1. Забронировать поездку для пассажиров
    Route::post('/bookings/for/passenger', [BookingController::class, 'storeForPassenger']);
    // 1. Принять брон от пассажира если предложил цену
    Route::post('/bookings/{booking}/accept', [BookingController::class, 'accept']);
    // 1. Удалять брон от пассажира если предложил цену
    Route::post('/bookings/{booking}/delete', [BookingController::class, 'delete']);

    // 2. Отменить свой бронь на заказ для водителей
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    // 2. Отменить свой бронь для пассажиров на поездку
    Route::post('/bookings/{booking}/for/passengers/cancel', [BookingController::class, 'cancelForPassengers']);
    // 3. Мои запросы in_progress
    Route::get('/bookings/my/in-progress', [BookingController::class, 'myInProgress']);
    // свои брони на поездку для пассажиров
    Route::get('/bookings/my/for/passenger/in-progress', [BookingController::class, 'myInProgressForPassengers']);
    // 4. Мои запросы completed
    Route::get('/bookings/my/completed', [BookingController::class, 'myCompleted']);

    Route::delete('/user/delete-account', [UserController::class, 'deleteAccount']); //удалить аккаунт

    // Для истории пассажиров принятие
    Route::get('/passenger/history/1', [HistoryController::class, 'passengerHistory1']);
    // Для истории пассажиров брони
    Route::get('/passenger/history/2', [HistoryController::class, 'passengerHistory2']);

    // Для истории водителй принятие
    Route::get('/driver/history/1', [HistoryController::class, 'driverHistory1']);
    // Для истории водителй брони
    Route::get('/driver/history/2', [HistoryController::class, 'driverHistory2']);

    Route::post('/car/driver', [CarController::class, 'store']);
    Route::get('/car', [CarController::class, 'show']);

    //Выйти
    Route::post('/logout', [AuthController::class, 'logout']); //выход

});
