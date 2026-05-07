<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid email or password.',
                ], 401);
            }

            $user = User::where('email', $request->email)->first();

            if (isset($user->is_active) && !$user->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account is currently inactive. Please contact support.',
                ], 403);
            }


            $token = $user->createToken('auth_token')->plainTextToken;


            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ?? 'staff',
                        'avatar' => $user->profile_picture ?? null,
                    ],
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Login failed due to a server error.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function clients(Request $request)
    {
        try {
            $user = $request->user();

            $client['total'] = Client::count();
            $client['active'] = Client::where('is_active', 1)->count();
            $client['inactive'] = Client::where('is_active', 0)->count();
            $client['client_data'] = Client::with('staff', 'contact', 'account_officer:id,name,email,phone')->latest()->get();

            return response()->json(['status' => true, 'data' => $client], 200);
        } catch (\Exception $e) {
            \Log::error('List Client Error: '.$e->getMessage());

            return response()->json(['status' => false, 'message' => 'An internal server error occurred.'], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }
}
