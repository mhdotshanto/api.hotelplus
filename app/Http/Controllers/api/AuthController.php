<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:30',
            'user_name' => 'required|string|max:30|unique:users',
            'role' => 'required',
            'type' => 'required',
            'status' => 'nullable',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|max:20|unique:users',
            'designation' => 'required|max:100',
            'password' => 'required|string|min:6|confirmed',
            'is_ref' => 'nullable',
        ]);

        $role = $request->type === 'agent' ? 'agent' : $request->role;

        $user = User::create([
            'name' => $request->name,
            'user_name' => $request->user_name,
            'role' => $role,
            'type' => $request->type,
            'status' => 'active',
            'email' => $request->email,
            'phone' => $request->phone,
            'designation' => $request->designation,
            'password' => Hash::make($request->password),
            'is_ref' => true,
        ]);

        return response()->json([
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'credential' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->credential)
            ->orWhere('user_name', $request->credential)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if($user->status === 'suspended' || $user->status === 'closed'){
            throw ValidationException::withMessages([
                'email' => ["Your account is {$user->status} currently."],
            ]);
        }

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'role'),
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
