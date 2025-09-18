<?php

use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;


Route::get('/test-eskiz', function () {
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(10)->get('https://notify.eskiz.uz/api/auth/login');
        return $response->body();
    } catch (\Exception $e) {
        return $e->getMessage();
    }
});

Route::post('/register', [VerifyController::class, 'registerStepOne']);
Route::post('/verify', [VerifyController::class, 'verifySmsAndActivate']);

// Шаг 1: отправка SMS
Route::post('/reset-password/step-one', [AuthController::class, 'resetPasswordStepOne']);

// Шаг 2: подтверждение кода и смена пароля
Route::post('/reset-password/step-two', [AuthController::class, 'resetPasswordStepTwo']);

Route::post('/login', [AuthController::class, 'login']); //зайти

// Защищённые маршруты
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'me']); //Получения данных пользователя
    Route::patch('/user', [UserController::class, 'update']);
    Route::patch('/user/password', [AuthController::class, 'changePassword']); //смена пароль в профиле
    //Trips - Поездки
    Route::post('/trip', [TripController::class, 'store']); // создать
    Route::get('/my-trips', [TripController::class, 'myTrips']); //мои поездки
    Route::get('/trips', [TripController::class, 'index']); //все поездки
    Route::patch('/trips/{trip}', [TripController::class, 'update']);   // обновить поездку
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']); // удалить поездку
    Route::post('/trips/{trip}/complete', [TripController::class, 'complete']); // завершить поездку
    //Bookings - Броны
    Route::post('/trips/{trip}/booking', [BookingController::class, 'store']); //бронировать
    Route::patch('/bookings/{booking}', [BookingController::class, 'update']); //бронировать
    Route::get('/bookings', [BookingController::class, 'myBookings']); // мои заявки
    Route::get('/trips/{trip}/bookings', [BookingController::class, 'tripBookings']); // заявки на мою поездку
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']); // пассажир отменяет
    //Messages - Чаты
    Route::post('/chats/{trip}/send', [ChatController::class, 'sendMessage']); //отправить сообщение
    Route::get('/chats/{trip}/with/{receiver}', [ChatController::class, 'getChatMessages']); //получить чат
    Route::get('/chats', [ChatController::class, 'getUserChats']); //все чаты пользователя
    Route::get('/chats/unread-count', [ChatController::class, 'unreadCount']); //непрочитанные сообщение
    //Notifications - Уведомление
    Route::get('/notifications', [NotificationController::class, 'index']); // все уведомления
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']); // количество непрочитанных
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']); // отметить как прочитанное
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']); // прочитать все

    //Ratings - Оценка
    Route::post('/ratings/{trip}/to/{toUser}', [RatingController::class, 'rateUser']);  //поставить оценку
    Route::get('/ratings/user/{user}', [RatingController::class, 'getUserRatings']);  //отзывы пользователя
    Route::get('/ratings/given', [RatingController::class, 'getMyRatingsGiven']); //мои отзывы

    //Выйти
    Route::post('/logout', [AuthController::class, 'logout']); //выход

});
