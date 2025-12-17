<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));

// Broadcasting авторизация для приватных каналов
Broadcast::routes(['middleware' => ['auth:sanctum']]);

