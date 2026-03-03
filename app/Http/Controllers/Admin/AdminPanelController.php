<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPanelController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Пополнение / списание баланса
    |--------------------------------------------------------------------------
    | phone: 901234567
    | amount: 50000
    | action: add / subtract
    */
    public function updateBalance(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'amount' => 'required|numeric|min:1',
            'action' => 'required|in:add,subtract',
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        DB::transaction(function () use ($user, $data) {

            if ($data['action'] === 'add') {
                $user->increment('balance', $data['amount']);
            }

            if ($data['action'] === 'subtract') {
                $user->decrement('balance', $data['amount']);
            }
        });

        return response()->json([
            'message' => 'Balance updated successfully',
            'new_balance' => $user->fresh()->balance,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Изменение роли пользователя
    |--------------------------------------------------------------------------
    | phone: 901234567
    | role: driver / passenger / admin
    */
    public function updateUserRole(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'role'  => 'required|in:passenger,driver',
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user->update([
            'role' => $data['role']
        ]);

        return response()->json([
            'message' => 'Role updated successfully',
            'user' => $user,
        ]);
    }

    public function sendMessageAll(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'role' => 'nullable|in:driver,passenger',
        ]);

        $query = User::whereNotNull('telegram_chat_id');

        // Если передали role → фильтруем
        if (!empty($data['role'])) {
            $query->where('role', $data['role']);
        }

        $totalSent = 0;

        $query->chunk(500, function ($users) use ($data, &$totalSent) {

            foreach ($users as $user) {
                dispatch(new SendTelegramNotificationJob(
                    $user->telegram_chat_id,
                    $data['message']
                ));

                $totalSent++;
            }
        });

        if ($totalSent === 0) {
            return response()->json([
                'message' => 'No users found'
            ]);
        }

        return response()->json([
            'message' => 'Broadcast sent successfully',
            'total_sent' => $totalSent,
        ]);
    }

    public function sendToUser(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'message' => 'required|string|max:2000',
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if (!$user->telegram_chat_id) {
            return response()->json([
                'message' => 'User has no Telegram connected'
            ], 422);
        }

        dispatch(new SendTelegramNotificationJob(
            $user->telegram_chat_id,
            $data['message']
        ));

        return response()->json([
            'message' => 'Message sent successfully',
        ]);
    }
}
