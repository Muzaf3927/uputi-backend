<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;

Route::get('/', fn () => view('welcome'))->name('home');

// Регистрация
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register.form');
Route::post('/register', [AuthController::class, 'register'])->name('register');

// Вход
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Выход
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


Route::resource('trips', TripController::class)->only(['index', 'create', 'store']);
Route::post('/trips/{trip}/book', [BookingController::class, 'store'])->name('bookings.store');
Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
