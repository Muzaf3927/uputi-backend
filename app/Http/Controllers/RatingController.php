<?php

namespace App\Http\Controllers;

use App\Models\Booking;
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

//        if ($fromUser->id === $toUser->id) {
//            return response()->json(['message' => 'You cannot rate yourself.'], 422);
//        }

        // Trip must be completed
        if ($trip->status !== 'completed') {
            return response()->json(['message' => 'You can rate only after trip completion.'], 422);
        }

        // Check participation and direction (driver -> passenger or passenger -> driver)
        $fromIsDriver = $trip->user_id === $fromUser->id;
        $toIsDriver = $trip->user_id === $toUser->id;

        // from passenger to driver
        $fromIsConfirmedPassenger = Booking::where('trip_id', $trip->id)
            ->where('user_id', $fromUser->id)
            ->where('status', 'confirmed')
            ->exists();

        // to passenger must have confirmed booking
        $toIsConfirmedPassenger = Booking::where('trip_id', $trip->id)
            ->where('user_id', $toUser->id)
            ->where('status', 'confirmed')
            ->exists();

        $allowed = false;
        if ($fromIsDriver && !$toIsDriver && $toIsConfirmedPassenger) {
            $allowed = true; // driver rating passenger
        }
        if (!$fromIsDriver && $toIsDriver && $fromIsConfirmedPassenger) {
            $allowed = true; // passenger rating driver
        }

        if (!$allowed) {
            return response()->json(['message' => 'You are not allowed to rate this user for this trip.'], 403);
        }

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

        // Update target user's aggregate rating
        $newCount = (int) $toUser->rating_count + 1;
        $currentAverage = (float) $toUser->rating; // decimal(2,1)
        $newAverage = $newCount > 0
            ? round((($currentAverage * (int) $toUser->rating_count) + (int) $request->rating) / $newCount, 1)
            : (float) $request->rating;

        $toUser->rating_count = $newCount;
        $toUser->rating = $newAverage;
        $toUser->save();

        return response()->json([
            'message' => 'Otsenka uspeshno sohranena.',
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

