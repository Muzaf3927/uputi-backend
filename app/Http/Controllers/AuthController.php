<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|size:9|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'phone.unique' => 'Вы раньше зарегистрировались с этим номером',
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Автоматическая выдача токена после успешной регистрации
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Регистрация прошла успешно',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required|size:9',
            'password' => 'required',
        ], [
            'phone.required' => 'Номер телефона обязателен',
            'phone.size'     => 'Номер телефона должен состоять из 9 цифр',
            'password.required' => 'Пароль обязателен',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Неверный логин или пароль'], 401);
        }

        // Удаляем старые токены
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Вход выполнен успешно',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone'    => 'required|size:9|exists:users,phone',
            'password' => 'required|min:6|confirmed',
        ], [
            'phone.required'     => 'Номер телефона обязателен',
            'phone.size'         => 'Номер телефона должен состоять из 9 цифр',
            'phone.exists'       => 'Пользователь с таким номером не найден',
            'password.required'  => 'Пароль обязателен',
            'password.min'       => 'Пароль должен содержать минимум 6 символов',
            'password.confirmed' => 'Пароли не совпадают',
        ]);

        $user = User::where('phone', $request->phone)->first();

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Пароль успешно обновлён',
            'success' => true,
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вы вышли из системы']);
    }
}
