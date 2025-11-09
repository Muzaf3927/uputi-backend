<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        $update = $request->all();
        Log::info('tg update', $update);

        $message = $update['message'] ?? null;

        if (!$message) {
            return response('ok', 200);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if ($text === '/start') {

            $keyboard = [
                [
                    [
                        'text' => 'UPuti programmasini ochish 🚗',
                        'web_app' => ['url' => 'https://www.uputi.net']
                    ]
                ]
            ];

            Http::post("https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/sendMessage", [
                'chat_id' => $chatId,
                'text' => "UPuti ga xush kelibsiz! UPuti programmasini ochish uchun pastdagi tugmaga bosing",
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'resize_keyboard' => true
                ]),
            ]);
        }

        return response('ok', 200);
    }
}
