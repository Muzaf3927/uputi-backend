<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TelegramConnectController extends Controller
{
    public function connect()
    {
        $botName = config('services.telegram.bot_name');

        $url = "https://t.me/{$botName}?start=user_" . auth()->id();

        return response()->json([
            'telegram_connect_url' => $url
        ]);
    }
}

