<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ChatMessage;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class ChatController extends Controller
{
    // 📩 Отправка сообщения
    public function sendMessage(Request $request, Trip $trip)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        $message = ChatMessage::create([
            'trip_id' => $trip->id,
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
        ]);

        // ➕ Создаем уведомление
        Notification::create([
            'user_id' => $request->receiver_id,
            'sender_id' => Auth::id(),
            'type' => 'chat',
            'message' => 'Новое сообщение в поездке ' . $trip->from_city . ' → ' . $trip->to_city,
            'data' => json_encode([
                'trip_id' => $trip->id,
                'chat_message_id' => $message->id
            ]),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $message
        ]);
    }

    // 📜 Получение всех сообщений между текущим юзером и другим пользователем по конкретной поездке
    public function getChatMessages(Trip $trip, User $receiver)
    {
        $userId = Auth::id();

        // Получаем сообщения
        $messages = ChatMessage::where('trip_id', $trip->id)
            ->where(function ($query) use ($userId, $receiver) {
                $query->where(function ($q) use ($userId, $receiver) {
                    $q->where('sender_id', $userId)
                        ->where('receiver_id', $receiver->id);
                })->orWhere(function ($q) use ($userId, $receiver) {
                    $q->where('sender_id', $receiver->id)
                        ->where('receiver_id', $userId);
                });
            })
            ->orderBy('created_at')
            ->get();

        // 🟢 Обновим все входящие сообщения (receiver — текущий пользователь)
        ChatMessage::where('trip_id', $trip->id)
            ->where('sender_id', $receiver->id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'messages' => $messages
        ]);
    }

    // 💬 Получение всех чатов пользователя
    public function getUserChats()
    {
        $userId = Auth::id();

        $chats = ChatMessage::query()
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                    ->orWhere('receiver_id', $userId);
            })
            ->selectRaw('
                trip_id,
                CASE
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as chat_partner_id,
                MAX(created_at) as last_message_at
            ', [$userId])
            ->groupBy('trip_id', 'chat_partner_id')
            ->orderByDesc('last_message_at')
            ->get();

        // Подгружаем инфу о собеседнике и поездке
        $chats = $chats->map(function ($chat) {
            $chat->partner = User::select('id', 'name', 'avatar')
                ->find($chat->chat_partner_id);
            $chat->trip = Trip::find($chat->trip_id);
            return $chat;
        });

        return response()->json([
            'status' => 'success',
            'chats' => $chats
        ]);
    }
    public function unreadCount()
    {
        $count = ChatMessage::where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
