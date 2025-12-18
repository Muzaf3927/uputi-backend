<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Broadcast routes
        |--------------------------------------------------------------------------
        |
        | Так как у тебя API + Bearer Token (WebView),
        | используем middleware `api`
        |
        */

        Broadcast::routes([
            'middleware' => ['api'],
        ]);

        require base_path('routes/channels.php');
    }
}
