<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('user_name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('email', 'LIKE', '%' . $request->search . '%');
            });
        }

        $data = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getUser($id)
    {
        $user = User::find($id);

        if ($user) {
            return response()->json([
                'success' => true,
                'data' => $user,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'User not found',
        ], 404);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:30',
            'user_name' => [
                'required',
                'max:30', 
                Rule::unique('users')->ignore($user->id)
            ],
            'role' => 'required',
            'type' => 'required',
            'email' => [
                'required',
                'email', 
                'max:255', 
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => [
                'required', 
                'max:20', 
                Rule::unique('users')->ignore($user->id)
            ],
            'designation' => 'required|max:100',
        ]);

        $role = $request->type === 'agent' ? 'agent' : $validated['role'];

        $user->update([
            'name' => $validated['name'],
            'user_name' => $validated['user_name'],
            'role' => $role,
            'type' => $validated['type'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'designation' => $validated['designation'],
        ]);

        return response()->json([
            'message' => 'User updated successfully!',
            'user' => $user
        ], 200);
    }

    public function changeStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,closed',
        ]);

        $user->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'User status changed successfully!',
            'user' => $user
        ], 200);
    }
}
