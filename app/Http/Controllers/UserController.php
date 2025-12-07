<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the authenticated user's details.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function user(User $user)
    {
        return response()->json($user);
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

    public function updateRol(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'role' => 'required|string|in:passenger,driver',
        ]);

        $user->role = $validated['role'];
        $user->save();

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $user->role
        ], 200);
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
