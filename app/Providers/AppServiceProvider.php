<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use App\Http\Responses\JsonResponse;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Response::macro('json', function ($data = [], $status = 200, array $headers = [], $options = 0) {
            return new JsonResponse($data, $status, $headers, $options);
        });
    }
}
