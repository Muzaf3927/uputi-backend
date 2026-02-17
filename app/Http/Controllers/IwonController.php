<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class IwonController extends Controller
{

    public function callback(Request $request)
    {
        return response()->json(['received' => true], 200);
    }



}
