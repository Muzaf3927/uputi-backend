<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    // Поставить оценку пользователю за поездку
    public function rateUser(Request $request, Trip $trip, User $toUser)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        $fromUser = Auth::user();

        // запрет на повторную оценку
        $existing = Rating::where('from_user_id', $fromUser->id)
            ->where('to_user_id', $toUser->id)
            ->where('trip_id', $trip->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already left a review for this user for this trip.'], 422);
        }

        $rating = Rating::create([
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'trip_id' => $trip->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Rating saved successfully.',
            'rating' => $rating
        ]);
    }

    // Получить отзывы на конкретного пользователя
    public function getUserRatings(User $user)
    {
        $ratings = Rating::where('to_user_id', $user->id)
            ->with('fromUser:id,name') // чтобы показать, кто оценил
            ->latest()
            ->paginate(10);

        $average = Rating::where('to_user_id', $user->id)->avg('rating');

        return response()->json([
            'average_rating' => round($average, 2),
            'ratings' => $ratings
        ]);
    }

    // Мои отзывы другим
    public function getMyRatingsGiven()
    {
        $ratings = Rating::where('from_user_id', Auth::id())
            ->with('fromUser:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'ratings_given' => $ratings
        ]);
    }
}

