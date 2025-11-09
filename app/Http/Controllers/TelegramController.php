<?php

namespace App\Http\Controllers;

use Telegram\Bot\Api;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $update = $telegram->getWebhookUpdate();

        $message = $update->getMessage();
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if ($text === '/start') {
            $keyboard = [
                [
                    ['text' => 'Открыть приложение 🚗', 'web_app' => ['url' => 'https://www.uputi.net']]
                ]
            ];

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Добро пожаловать в UPuti! Нажми кнопку ниже 👇",
                'reply_markup' => json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true])
            ]);
        }

        return response('ok', 200);
    }
}
