<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\TelegramRegistration;
use App\Services\TelegramService;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = new TelegramService();
        $update = $request->all();

        if (!isset($update['message']['chat']['id'])) {
            return response('ok');
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? null;
        $contact = $message['contact'] ?? null;

        $reg = TelegramRegistration::firstOrCreate(
            ['chat_id' => (string) $chatId],
            ['step' => 'start']
        );

        // /start — начало регистрации
        if ($text === '/start') {
            $reg->update(['step' => 'phone', 'phone' => null, 'name' => null]);
            $telegram->requestContact($chatId, "🚐 <b>uPuti — Ro'yxatdan o'tish</b>\n\n📱 Telefon raqamingizni yuboring:");
            return response('ok');
        }

        // Шаг 1: Получаем телефон
        if ($reg->step === 'phone') {
            if ($contact) {
                $phone = ltrim($contact['phone_number'], '+');
                if (str_starts_with($phone, '998')) {
                    $phone = substr($phone, 3);
                }
                $reg->update(['phone' => $phone, 'step' => 'name']);
                $telegram->removeKeyboard($chatId, "✅ Raqam qabul qilindi: <b>{$phone}</b>\n\nIsmingizni kiriting:");
            } else {
                $telegram->requestContact($chatId, "📱 Iltimos, tugmani bosib telefon raqamingizni yuboring:");
            }
            return response('ok');
        }

        // Шаг 2: Получаем имя
        if ($reg->step === 'name') {
            if (!$text || mb_strlen($text) < 2) {
                $telegram->sendMessage($chatId, "Iltimos, to'g'ri ism kiriting (kamida 2 ta belgi).");
                return response('ok');
            }
            $reg->update(['name' => $text, 'step' => 'password']);
            $telegram->sendMessage($chatId, "🔐 Parol o'rnating (kamida 6 ta belgi):");
            return response('ok');
        }

        // Шаг 3: Получаем пароль и создаём/обновляем юзера
        if ($reg->step === 'password') {
            if (!$text || mb_strlen($text) < 6) {
                $telegram->sendMessage($chatId, "Parol kamida 6 ta belgidan iborat bo'lishi kerak. Qaytadan kiriting:");
                return response('ok');
            }

            $data = [
                'name' => $reg->name,
                'password' => Hash::make($text),
                'phone' => $reg->phone,
                'telegram_chat_id' => $chatId,
            ];

            $user = User::where('phone', $reg->phone)->orWhere('telegram_chat_id', $chatId)->first();

            if ($user) {
                $user->update($data);
            } else {
                User::create($data);
            }

            $reg->update(['step' => 'done']);

            $telegram->sendMessage($chatId,
                "✅ <b>Ro'yxatdan muvaffaqiyatli o'tdingiz!</b>\n\n" .
                "Ilovaga qaytib quyidagi ma'lumotlar bilan kiring:\n\n" .
                "📱 Telefon: <b>{$reg->phone}</b>\n" .
                "🔐 Parol: <b>{$text}</b>\n\n" .
                "Qayta ro'yxatdan o'tish uchun /start bosing."
            );

            return response('ok');
        }

        // Если step = done или неизвестное — предлагаем /start
        $telegram->sendMessage($chatId, "Qayta ro'yxatdan o'tish uchun /start bosing.");

        return response('ok');
    }
}
