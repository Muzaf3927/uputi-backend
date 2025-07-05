<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ChatMessage;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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

        // 💬 Сообщение отправлено
        return response()->json([
            'status' => 'success',
            'message' => $message
        ]);
    }

    // 📜 Получение всех сообщений между текущим юзером и другим пользователем по конкретной поездке
    public function getChatMessages(Trip $trip, User $receiver)
    {
        $userId = Auth::id();


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

        return response()->json([
            'status' => 'success',
            'messages' => $messages
        ]);
    }
}
