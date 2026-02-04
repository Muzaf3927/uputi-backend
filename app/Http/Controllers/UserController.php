<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(
            $request->user()->load('car')
        );
    }


    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|size:9|unique:users,phone,' . $user->id,
            'avatar' => 'sometimes|string|max:255',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

//    public function updateRole(Request $request)
//    {
//        $validated = $request->validate([
//            'role' => 'required|in:passenger,driver',
//        ]);
//
//        $request->user()->update([
//            'role' => $validated['role'],
//        ]);
//
//
//        return response()->json([
//            'message' => 'Role updated successfully',
//            'role' => $validated['role'],
//        ]);
//    }
    public function updateRole(Request $request)
    {
        $validated = $request->validate([
            'role' => 'required|in:passenger,driver',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated) {

            if ($validated['role'] === 'driver' && $user->role !== 'driver') {

                $alreadyGiven = UserGift::where('user_id', $user->id)
                    ->where('type', 'driver_welcome_bonus')
                    ->exists();

                if (!$alreadyGiven) {
                    $user->increment('balance', 50000);

                    UserGift::create([
                        'user_id' => $user->id,
                        'type'    => 'driver_welcome_bonus',
                        'amount'  => 50000,
                    ]);
                }
            }

            $user->update([
                'role' => $validated['role'],
            ]);
        });

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $validated['role'],
        ]);
    }

    /**
     * Delete the authenticated user's account (soft delete).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        if (in_array($user->phone, ['123123123'])) {
            return response()->json([
                'message' => 'Account deleted successfully. Your data has been preserved for analytics purposes..'
            ]);
        }

        // Soft delete the user account
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully. Your data has been preserved for analytics purposes.'
        ]);
    }
}
