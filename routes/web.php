<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TelegramController;

Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));

