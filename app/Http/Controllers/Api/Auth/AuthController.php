<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'role'         => ['required', 'string', Rule::in(['customer', 'driver', 'partner_admin', 'admin'])],
            'device_name'  => ['nullable', 'string', 'max:100'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            return User::create([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'phone_number' => $validated['phone_number'] ?? null,
                'password'     => Hash::make($validated['password']),
                'role'         => $validated['role'],
            ]);
        });

        $tokenName = $request->input('device_name', 'api-token');
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'token'   => $token,
            'user'    => $user->only([
                'id',
                'name',
                'email',
                'phone_number',
                'role',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]),
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'         => ['required', 'string', 'email'],
            'password'      => ['required', 'string'],
            'device_name'   => ['nullable', 'string', 'max:100'],
            'revoke_others' => ['sometimes', 'boolean'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($request->boolean('revoke_others', false)) {
            $user->tokens()->delete();
        }

        $tokenName = $request->input('device_name', 'api-token');
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user->only([
                'id',
                'name',
                'email',
                'phone_number',
                'role',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user'    => $user?->only([
                'id',
                'name',
                'email',
                'phone_number',
                'role',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
        ]);
    }
}
