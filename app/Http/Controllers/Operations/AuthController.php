<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEmployee;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeDeployment;
use App\Models\EmployeeDocument;
use App\Models\EquipmentMovement;
use App\Models\PaySlip;
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

    public function staff(Request $request)
    {
        try {

            $staff['total'] = Employee::count();
            $staff['onfield'] = Employee::where('staff_type', 'field')->count();
            $staff['deployed'] = Employee::where('deployed', 1)->count();
            $staff['inactive'] = Employee::where('is_active', 0)->count();
            $staff['staff_data'] = Employee::latest()->get();

            return response()->json(['status' => true, 'staff' => $staff], 200);
        } catch (\Exception $e) {
            \Log::error('List Staff Error: '.$e->getMessage());

            return response()->json(['status' => false, 'message' => 'An internal server error occurred.'], 500);
        }
    }

    public function staffDetails(Request $request, $id)
    {
        try {


            // Fetch Staff Details, Documents, and Equipment
            $staff['details'] = Employee::whereId($id)->with('department')->first();
            $staff['documents'] = EmployeeDocument::where('employee_id', $id)->latest()->get();
            $staff['equipment'] = EquipmentMovement::where('employee_id', $id)->with('equipment')->latest()->get();
            $staff['attendance'] = AttendanceEmployee::where('employee_id', $id)->latest()->get();

            $payment['total_salary_received'] = PaySlip::where('employee_id', $id)->sum('basic_salary');
            $payment['all_payments'] = PaySlip::where('employee_id', $id)->latest()->get();

            // --- Deployment Calculations ---

            $durationResult = EmployeeDeployment::where('employee_id', $id)->sum('hours');
            $deployment['deployment_duration'] = $durationResult ?? 0;

            // 1. Get Current/Latest Deployment WITH Client Object
            // Using with('client') attaches the Client model as an object
            $deployment['current_deployment'] = EmployeeDeployment::where('employee_id', $id)
                ->with('client')
                ->latest()
                ->first();

            // 2. Get All Deployments WITH Client Objects
            $deployment['deployments'] = EmployeeDeployment::where('employee_id', $id)
                ->with('client')
                ->latest()->get();

            $data = array_merge($staff, ['deployment' => $deployment, 'payment' => $payment]);

            return response()->json(['status' => true, 'data' => $data], 200);
        } catch (\Exception $e) {
            \Log::error('Staff Details Error: '.$e->getMessage());

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
