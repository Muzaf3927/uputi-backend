<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AccountDeletionController extends Controller
{
    public function apiDeleteByCredentials(Request $request)
    {
        $request->validate([
            'phone' => 'required|size:9',
            'password' => 'required',
        ]);

        if (in_array($request->phone, ['123123123'])) {
            return response()->json(['message' => 'Account deleted successfully']);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid phone or password'], 401);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}


