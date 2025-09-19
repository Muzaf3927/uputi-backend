<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Response::macro('json', function ($data = [], $status = 200, array $headers = [], $options = 0) {
            $options = $options | config('app.json_encode_options', 0);
            return response()->json($data, $status, $headers, $options);
        });
    }
}
