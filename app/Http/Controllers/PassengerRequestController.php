<?php

namespace App\Http\Controllers;

use App\Models\PassengerRequest;
use App\Models\DriverOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\LatinToCyrillic;

class PassengerRequestController extends Controller
{
    // Создать запрос пассажира
    public function store(Request $request)
    {
        $request->validate([
            'from_lat' => 'nullable|numeric',
            'from_lng' => 'nullable|numeric',
            'from_address' => 'nullable|string|max:255',
            'to_lat' => 'nullable|numeric',
            'to_lng' => 'nullable|numeric',
            'to_address' => 'nullable|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'amount' => 'nullable|integer|min:0',
            'seats' => 'required|integer|min:1',
            'comment' => 'nullable|string|max:500',
        ]);

        $passengerRequest = PassengerRequest::create([
            'user_id' => Auth::id(),
            'from_lat' => $request->from_lat,
            'from_lng' => $request->from_lng,
            'from_address' => $request->from_address,
            'to_lat' => $request->to_lat,
            'to_lng' => $request->to_lng,
            'to_address' => $request->to_address,
            'date' => $request->date,
            'time' => $request->time,
            'amount' => $request->amount,
            'seats' => $request->seats,
            'comment' => $request->comment,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Passenger request created!',
            'passenger_request' => $passengerRequest,
        ], 201);
    }

    // Мои запросы
    public function myRequests(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $requests = PassengerRequest::where('user_id', Auth::id())
            ->with('driverOffers.driver', 'passenger:id,name,phone,rating')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($requests);
    }

    // Все запросы (для водителей)
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $requests = PassengerRequest::where('status', 'active')
            ->with('passenger:id,name,phone,rating', 'driverOffers.driver')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('time')
            ->paginate($perPage);

        return response()->json($requests);
    }

    // Обновить запрос
    public function update(Request $request, PassengerRequest $passengerRequest)
    {
        if ($passengerRequest->user_id !== Auth::id()) {
            return response()->json(['message' => 'No access'], 403);
        }

        $validated = $request->validate([
            'from_lat' => 'nullable|numeric',
            'from_lng' => 'nullable|numeric',
            'from_address' => 'nullable|string|max:255',
            'to_lat' => 'nullable|numeric',
            'to_lng' => 'nullable|numeric',
            'to_address' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'time' => 'nullable',
            'amount' => 'nullable|integer|min:0',
            'seats' => 'nullable|integer|min:1',
            'comment' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,completed',
        ]);

        $passengerRequest->update($validated);

        return response()->json([
            'message' => 'Passenger request updated!',
            'passenger_request' => $passengerRequest,
        ]);
    }

    // Удалить запрос
    public function destroy(PassengerRequest $passengerRequest)
    {
        if ($passengerRequest->user_id !== Auth::id()) {
            return response()->json(['message' => 'No access'], 403);
        }

        $passengerRequest->delete();

        return response()->json([
            'message' => 'Passenger request deleted!',
        ]);
    }

    // Получить офферы на запрос
    public function getOffers(PassengerRequest $passengerRequest)
    {
        if ($passengerRequest->user_id !== Auth::id()) {
            return response()->json(['message' => 'No access'], 403);
        }

        // Альтернативный способ для отладки (если связь не работает)
         $offers = DriverOffer::where('passenger_request_id', $passengerRequest->id)
             ->with('driver:id,name,phone,rating')
             ->orderByDesc('created_at')
             ->get();

        return response()->json([
            'passenger_request' => $passengerRequest,
            'offers' => $offers,
            'offers_count' => $offers->count(), // для отладки
        ]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'from_lat' => 'nullable|numeric',
            'from_lng' => 'nullable|numeric',
            'from_address' => 'nullable|string|max:255',
            'to_lat' => 'nullable|numeric',
            'to_lng' => 'nullable|numeric',
            'to_address' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'time' => 'nullable',
        ]);

        $query = PassengerRequest::query();

        // только активные
        $query->where('status', 'active');


        if ($request->filled('from_lat') && $request->filled('from_lng')) {

            $query->where('from_lat', $request->from_lat)
                ->where('from_lng', $request->from_lng);

        } elseif ($request->filled('from_address')) {

            $search = $request->from_address;
            $converted = LatinToCyrillic::convert($search);

            $query->where(function ($q) use ($search, $converted) {
                $q->where('from_address', 'ILIKE', "%{$search}%")
                    ->orWhere('from_address', 'ILIKE', "%{$converted}%");
            });
        }

        if ($request->filled('to_lat') && $request->filled('to_lng')) {

            $query->where('to_lat', $request->to_lat)
                ->where('to_lng', $request->to_lng);

        } elseif ($request->filled('to_address')) {

            $search = $request->to_address;
            $converted = LatinToCyrillic::convert($search);

            $query->where(function ($q) use ($search, $converted) {
                $q->where('to_address', 'ILIKE', "%{$search}%")
                    ->orWhere('to_address', 'ILIKE', "%{$converted}%");
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('time')) {
            $query->where('time', '>=', $request->time);
        }

        $results = $query->orderBy('date')->orderBy('time')->get();

        return response()->json($results);
    }
}
