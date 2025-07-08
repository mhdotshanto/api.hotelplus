<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Models\Preference;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'preference' => 'required|string|max:25',
        ]);

        $preference = Preference::create([
            'name' => $validated['preference'],
        ]);

        return response()->json([
            'message' => 'Preference created successfully.',
            'data' => $preference,
        ], 201);
    }
}
