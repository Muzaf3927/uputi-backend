<?php

namespace App\Http\Controllers;

use App\Events\UserUpdated;
use Illuminate\Http\Request;
use App\Models\User;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();

        // Если это не сообщение, выходим
        if (!isset($update['message'])) {
            return response()->json(['ok' => true]);
        }

        $message = $update['message'];
        $chatId  = $message['chat']['id'];
        $text    = $message['text'];

        // Проверяем команду /start user_X
        if (str_starts_with($text, '/start user_')) {
            $userId = (int) str_replace('/start user_', '', $text);

            $user = User::find($userId);

            if ($user) {
                // сохраняем chat_id в базу
                $user->telegram_chat_id = $chatId;
                $user->save();

                // Отправляем событие через WebSocket для обновления данных пользователя на фронтенде
                event(new UserUpdated($user->load('car')));

                // отправляем приветственное сообщения
                $this->sendMessage($chatId, "🔔 Tabriklaymiz! Endi barcha yo'lovchi yoki haydovchi so'rovlari shu yerda aks etadi.\n\n" .
                    "🔔 Поздравляем! Теперь все запросы пассажиров и водителей будут отображаться здесь."
                );

            }
        }

        return response()->json(['ok' => true]);
    }

    private function sendMessage($chatId, $text)
    {
        $token = config('services.telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        file_get_contents($url . "?chat_id={$chatId}&text=" . urlencode($text));
    }
}

