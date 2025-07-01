<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TripController extends Controller
{
    public function index()
    {
        $trips = Trip::with('driver')->where('status', 'active')->orderBy('date')->get();
        return view('trips.index', compact('trips'));
    }

    public function create()
    {
        return view('trips.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_city' => 'required|string|max:255',
            'to_city' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'seats' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'note' => 'nullable|string'
        ]);

        Trip::create([
            'user_id' => Auth::id(),
            'from_city' => $request->from_city,
            'to_city' => $request->to_city,
            'date' => $request->date,
            'time' => $request->time,
            'seats' => $request->seats,
            'price' => $request->price,
            'note' => $request->note,
        ]);

        return redirect()->route('trips.index')->with('success', 'Поездка создана!');
    }
}

