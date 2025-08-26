<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::query();
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->search . '%');
            });
        }
        $users = $query->orderBy('id', 'ASC')
            ->paginate($request->get('per_page'));

        return response()->json($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:30',
            'user_name' => 'required|string|max:30|unique:users,user_name',
            'role' => 'required|in:super_admin,admin,developer,agent',
            'type' => 'required|in:agent,user',
            'status' => 'required|in:active,suspended,closed',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'designation' => 'nullable|string|max:100',
            'password' => 'required|string|min:8|confirmed',
            'is_ref' => 'nullable|boolean',
            'email_verified_at' => 'nullable|date',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        User::create($validated);

        return response()->json(['success' => true], 201);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:30',
            'user_name' => 'required|string|max:30|unique:users,user_name,' . $id,
            'role' => 'required|in:super_admin,admin,developer,agent',
            'type' => 'required|in:agent,user',
            'status' => 'required|in:active,suspended,closed',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'required|string|unique:users,phone,' . $id,
            'designation' => 'nullable|string|max:100',
            'is_ref' => 'nullable|boolean',
            'email_verified_at' => 'nullable|date',
        ]);
        $user->update($validated);
        return response()->json(['success' => true], 200);
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        //
    }

    public function updateUsersPassword(Request $request, User $user)
    {
        $validated = $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->update(['password' => Hash::make($validated['password'])]);
        return response()->json(['success' => true, 'message' => 'Password updated successfully'], 200);
    }

}
