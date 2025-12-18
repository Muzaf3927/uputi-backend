<?php
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));

