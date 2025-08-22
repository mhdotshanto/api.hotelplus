<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AccParty;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccPartyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json(
            AccParty::orderBy('created_at', 'ASC')
                ->paginate($request->get('per_page'))
        , 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:30',
            'relation_id' => 'nullable|integer|min:0',
        ]);

        // Auto-generate slug
        $validated['slug'] = Str::slug($validated['name']);

        $party = AccParty::create($validated);

        return response()->json($party, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AccParty $accParty)
    {
        return response()->json($accParty, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AccParty $accParty)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:30',
            'relation_id' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $accParty->update($validated);

        return response()->json($accParty, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccParty $accParty)
    {
        $accParty->delete();
        return response()->json(['message' => 'Deleted successfully.'], 204);
    }
}
