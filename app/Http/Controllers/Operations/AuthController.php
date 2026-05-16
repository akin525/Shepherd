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
use App\Models\SupervisorGuardAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    public function createSupervisor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'client_id' => 'required|integer|exists:clients,id',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'type' => 'supervisor',
                'created_by' => optional($request->user())->id,
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'staff_type' => 'supervisor',
                'created_by' => optional($request->user())->id,
            ]);

            $clientStaff = EmployeeDeployment::create([
                'client_id' => $request->client_id,
                'employee_id' => $employee->id,
                'status' => 1,
                'validity_period'=>30,
                'deployed_by'=>optional($request->user())->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Supervisor created successfully',
                'data' => [
                    'user' => $user,
                    'employee' => $employee,
                    'client_staff' => $clientStaff,
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create supervisor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignSupervisorToGuard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supervisor_user_id' => 'required|integer|exists:users,id',
            'guard_user_id' => 'required|integer|exists:users,id|different:supervisor_user_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $supervisor = User::find($request->supervisor_user_id);
            $guard = User::find($request->guard_user_id);

            if (!$supervisor || $supervisor->type !== 'supervisor') {
                return response()->json([
                    'status' => false,
                    'message' => 'Selected supervisor user is invalid. User type must be supervisor.',
                ], 422);
            }

            if (!$guard || $guard->type !== 'guard') {
                return response()->json([
                    'status' => false,
                    'message' => 'Selected guard user is invalid. User type must be guard.',
                ], 422);
            }

            $supervisorClientId = $this->resolveClientIdForUser($supervisor->id);
            $guardClientId = $this->resolveClientIdForUser($guard->id);

            if (!$supervisorClientId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Supervisor is not deployed to any client.',
                ], 422);
            }

            if (!$guardClientId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Guard is not deployed to any client.',
                ], 422);
            }

            if ($supervisorClientId !== $guardClientId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Supervisor and guard must belong to the same client.',
                    'data' => [
                        'supervisor_client_id' => $supervisorClientId,
                        'guard_client_id' => $guardClientId,
                    ],
                ], 422);
            }

            $assignment = SupervisorGuardAssignment::updateOrCreate(
                ['guard_user_id' => $guard->id],
                [
                    'client_id' => $guardClientId,
                    'supervisor_user_id' => $supervisor->id,
                    'assigned_by' => optional($request->user())->id,
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Guard assigned to supervisor successfully',
                'data' => $assignment,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to assign supervisor to guard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveClientIdForUser(int $userId): ?int
    {
        $employee = Employee::where('user_id', $userId)->first();
        if (!$employee) {
            return null;
        }

        $clientStaff = EmployeeDeployment::where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        return $clientStaff?->client_id ? (int) $clientStaff->client_id : null;
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
