<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | REGISTER ADMIN (with secret)
    |--------------------------------------------------------------------------
    */
    public function register(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'password' => 'required|min:6',
            'secret' => 'required',
        ]);

        if ($data['secret'] !== env('ADMIN_SECRET')) {
            return response()->json([
                'message' => 'Invalid secret key'
            ], 403);
        }

        $admin = Admin::where('phone', $data['phone'])->first();

        if ($admin) {
            // 🔄 обновляем пароль
            $admin->update([
                'password' => Hash::make($data['password'])
            ]);

            return response()->json([
                'message' => 'Admin password updated',
                'admin' => $admin,
            ]);
        }

        // 🆕 создаем нового
        $admin = Admin::create([
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => $admin,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN ADMIN
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'password' => 'required',
        ]);

        $admin = Admin::where('phone', $data['phone'])->first();

        if (!$admin || !Hash::check($data['password'], $admin->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 🔥 удаляем старые токены
        $admin->tokens()->delete();

        // 🔥 создаём новый токен
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'admin' => $admin,
        ]);
    }

    public function logout(Request $request)
    {
        $admin = $request->user('admin');

        if (!$admin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $admin->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
