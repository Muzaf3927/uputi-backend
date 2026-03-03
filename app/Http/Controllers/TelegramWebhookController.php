<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();

        // Если это не сообщение — выходим
        if (!isset($update['message'])) {
            return response()->json(['ok' => true]);
        }

        $message = $update['message'];

        // Проверяем наличие chat.id
        if (!isset($message['chat']['id'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'];

        // 🔥 Если нет текста — просто выходим
        if (!isset($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $text = $message['text'];

        // Проверяем команду /start user_X
        if (str_starts_with($text, '/start user_')) {

            $userId = (int) str_replace('/start user_', '', $text);
            $user = User::find($userId);

            if ($user) {

                // сохраняем chat_id
                $user->telegram_chat_id = $chatId;
                $user->save();

                // отправляем приветственное сообщение
                $this->sendMessage(
                    $chatId,
                    "🔔 Tabriklaymiz! Endi barcha yo'lovchi yoki haydovchi so'rovlari shu yerda aks etadi.\n\n" .
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
