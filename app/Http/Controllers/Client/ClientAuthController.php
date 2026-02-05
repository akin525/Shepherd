<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClientAuthController extends Controller
{
    /**
     * Handle user login request
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'device_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('type', 'client')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'The provided credentials are incorrect.'
            ]);
//            throw ValidationException::withMessages([
//                'email' => ['The provided credentials are incorrect.'],
//            ]);
        }

        // Create token for mobile device
        $token = $user->createToken($request->device_name)->plainTextToken;

        // Load user with employee information
        $user->load('employee');

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Handle user logout request
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user details
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['employee', 'employee.department', 'employee.designation']);

        return response()->json([
            'status' => true,
            'message' => 'User details retrieved successfully',
            'data' => [
                'user' => $user
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        // Update employee profile
        if ($employee) {
            $employee->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user->load('employee')
            ]
        ]);
    }

    public function sendOtp(Request $request)
    {
        $user = Auth::user();

        $otp = rand(1000, 9999);
         $user->otp = $otp;
        $user->save();

        try {

//            Mail::to($user->email)->send(new OtpMail($user, $otp));

            return response()->json([
                'status' => true,
                'message' => 'OTP sent successfully to ' . $user->email,
                'otp'=>$otp
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP email. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 2: Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:4'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP format',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if ($user->otp == $request->otp) {

            $user->otp = null;
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Identity verified successfully.'
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid or expired OTP.'
        ], 400);
    }

    /**
     * Step 3: Change Password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // 1. Verify Current Password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'The current password provided is incorrect.'
            ], 400);
        }

        // 2. Update Password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully.',
            'statusCode' => 200 // Matching your frontend check
        ]);
    }
}
