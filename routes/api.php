<?php

use App\Http\Controllers\ProfileController;
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
    Route::post('/user', [UserController::class, 'update']);
    //Trips - Поездки
    Route::post('/trip', [TripController::class, 'store']); // создать
    Route::get('/my-trips', [TripController::class, 'myTrips']); //мои поездки
    Route::get('/trips', [TripController::class, 'index']); //все поездки
    Route::post('/trips/{trip}', [TripController::class, 'update']);   // обновить поездку
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']); // удалить поездку
    Route::post('/trips/{trip}/complete', [TripController::class, 'complete']); // завершить поездку
    Route::get('/trips/completed/mine', [TripController::class, 'myCompletedTrips']); // мои завершенные поездки (как водитель)
    Route::get('/trips/completed/as-passenger', [TripController::class, 'myCompletedTripsAsPassenger']); // завершенные поездки, где я пассажир
    //Bookings - Броны
//    Route::post('/trips/{trip}/booking', [BookingController::class, 'store']); //бронировать
//    Route::post('/bookings/{booking}', [BookingController::class, 'update']); //бронировать
//    Route::get('/bookings', [BookingController::class, 'myBookings']); // мои заявки
//    Route::get('/trips/{trip}/bookings', [BookingController::class, 'tripBookings']); // заявки на мою поездку
//    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']); // пассажир отменяет
//    // Pending lists
//    Route::get('/bookings/pending/mine', [BookingController::class, 'myPendingBookings']); // мои ожидающие подтверждения
//    Route::get('/bookings/pending/to-my-trips', [BookingController::class, 'pendingBookingsToMyTrips']); // ожидающие ко мне

    Route::post('/trips/{trip}/booking', [BookingController::class, 'store']);// zabronirovat poezdku
    Route::post('/bookings/{booking}', [BookingController::class, 'update']);// obnovit status bronirovaniya (naprimer, prinyat ili otklonit)
    Route::get('/bookings', [BookingController::class, 'myBookings']);// spisok vseh moih zayavok (gde ya uchastvuyu)
    Route::get('/trips/{trip}/bookings', [BookingController::class, 'tripBookings']);// spisok vseh zayavok na moe poezdki
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);// otmenit moe bronirovanie (passazhir otmenyaet)
    Route::get('/bookings/pending/mine', [BookingController::class, 'myPendingBookings']);// spisok moih ozhidayushih podtverzhdeniya zayavok
    Route::get('/bookings/pending/to-my-trips', [BookingController::class, 'pendingBookingsToMyTrips']);// spisok zayavok, kotorye ozhidayut moego podtverzhdeniya
    //Messages - Чаты
    Route::post('/chats/{trip}/send', [ChatController::class, 'sendMessage']); //отправить сообщение
    Route::get('/chats/{trip}/with/{receiver}', [ChatController::class, 'getChatMessages']); //получить чат
    Route::get('/chats', [ChatController::class, 'getUserChats']); //все чаты пользователя
    Route::get('/chats/unread-count', [ChatController::class, 'unreadCount']); //непрочитанные сообщение
    //Notifications - Уведомление
    Route::get('/notifications', [NotificationController::class, 'index']); // все уведомления
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']); // количество непрочитанных
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']); // отметить как прочитанное
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']); // прочитать все

    //Ratings - Оценка
    Route::post('/ratings/{trip}/to/{toUser}', [RatingController::class, 'rateUser']);  //поставить оценку
    Route::get('/ratings/user/{user}', [RatingController::class, 'getUserRatings']);  //отзывы пользователя
    Route::get('/ratings/given', [RatingController::class, 'getMyRatingsGiven']); //мои отзывы

    //Profile
    Route::get('/users/me', [UserController::class, 'me']);
    Route::get('/users/{user}', [UserController::class, 'user']);

    //Выйти
    Route::post('/logout', [AuthController::class, 'logout']); //выход

});
