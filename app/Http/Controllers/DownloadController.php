<?php

namespace App\Http\Controllers;

use App\Models\Download;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:android,ios',
        ]);

        Download::create(['type' => $request->type]);

        return response()->json(['success' => true]);
    }
}
