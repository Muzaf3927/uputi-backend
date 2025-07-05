<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // Получить все настройки
    public function index()
    {
        return Setting::all();
    }

    // Получить одну настройку
    public function show($key)
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json(['message' => 'Настройка не найдена'], 404);
        }

        return $setting;
    }

    // Создать или обновить настройку
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'nullable|string'
        ]);

        $setting = Setting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $request->value]
        );

        return response()->json([
            'message' => 'Настройка сохранена',
            'setting' => $setting
        ]);
    }

    // Удалить настройку
    public function destroy($key)
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json(['message' => 'Настройка не найдена'], 404);
        }

        $setting->delete();

        return response()->json(['message' => 'Настройка удалена']);
    }
}

