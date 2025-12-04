<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Models\AttendanceEmployee;
use App\Models\Leave;
use App\Models\PaySlip;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department', 'designation', 'user'])
            ->active();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by department
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by designation
        if ($request->has('designation_id')) {
            $query->where('designation_id', $request->designation_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $employees = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Employees retrieved successfully',
            'data' => [
                'employees' => $employees,
                'filters' => [
                    'departments' => Department::pluck('name', 'id'),
                    'designations' => Designation::pluck('name', 'id'),
                ]
            ]
        ]);
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'user',
            'department',
            'designation',
            'branch',
            'documents',
            'assets'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Employee retrieved successfully',
            'data' => [
                'employee' => $employee
            ]
        ]);
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'employee_id' => 'required|string|max:255|unique:employees,employee_id',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'branch_id' => 'nullable|exists:branches,id',
            'gender' => 'required|in:male,female,other',
            'dob' => 'required|date',
            'date_of_joining' => 'required|date',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'salary' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|in:monthly,weekly,hourly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt('password123'), // Default password
                'type' => 'employee',
                'created_by' => Auth::id(),
            ]);

            // Create employee profile
            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'employee_id' => $request->employee_id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'branch_id' => $request->branch_id,
                'gender' => $request->gender,
                'dob' => $request->dob,
                'date_of_joining' => $request->date_of_joining,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'postal_code' => $request->postal_code,
                'marital_status' => $request->marital_status,
                'salary' => $request->salary,
                'salary_type' => $request->salary_type,
                'is_active' => true,
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            $employee->load(['user', 'department', 'designation']);

            return response()->json([
                'status' => true,
                'message' => 'Employee created successfully',
                'data' => [
                    'employee' => $employee
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:employees,email,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'branch_id' => 'nullable|exists:branches,id',
            'gender' => 'required|in:male,female,other',
            'dob' => 'required|date',
            'date_of_joining' => 'required|date',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'salary' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|in:monthly,weekly,hourly',
            'is_active' => 'boolean',
            'status' => 'in:active,inactive,terminated,resigned',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update user
            if ($employee->user) {
                $employee->user->update([
                    'name' => $request->name,
                    'email' => $request->email,
                ]);
            }

            // Update employee
            $employee->update($request->except('user_id', 'created_by'));

            DB::commit();

            $employee->load(['user', 'department', 'designation']);

            return response()->json([
                'status' => true,
                'message' => 'Employee updated successfully',
                'data' => [
                    'employee' => $employee
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Soft delete by updating status
            $employee->update([
                'is_active' => false,
                'status' => 'terminated'
            ]);

            // Deactivate user account
            if ($employee->user) {
                $employee->user->tokens()->delete(); // Revoke all tokens
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Employee deactivated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to deactivate employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee attendance records.
     */
    public function attendance(Employee $employee, Request $request): JsonResponse
    {
        $query = $employee->attendances()->with('employee');

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Filter by month
        if ($request->has('month')) {
            $query->whereMonth('date', $request->month);
        }

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('date', $request->year);
        }

        $attendances = $query->orderBy('date', 'desc')
                            ->paginate($request->get('per_page', 30));

        return response()->json([
            'status' => true,
            'message' => 'Employee attendance retrieved successfully',
            'data' => [
                'attendances' => $attendances,
                'summary' => [
                    'total_days' => $attendances->count(),
                    'present_days' => $employee->attendances()->where('status', 'Present')->count(),
                    'absent_days' => $employee->attendances()->where('status', 'Absent')->count(),
                    'late_days' => $employee->attendances()->where('late', 'Yes')->count(),
//                    'half_days' => $employee->attendances()->where('half_day', 'Yes')->count(),
                ]
            ]
        ]);
    }

    /**
     * Get employee leave records.
     */
    public function leaves(Employee $employee, Request $request): JsonResponse
    {
        $query = $employee->leaves()->with('leaveType');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $leaves = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => true,
            'message' => 'Employee leaves retrieved successfully',
            'data' => [
                'leaves' => $leaves,
                'summary' => [
                    'total_leaves' => $leaves->count(),
                    'approved_leaves' => $employee->leaves()->where('status', 'Approved')->count(),
                    'pending_leaves' => $employee->leaves()->where('status', 'Pending')->count(),
                    'rejected_leaves' => $employee->leaves()->where('status', 'Reject')->count(),
                    'leave_balance' => $employee->leave_balance ?? 0,
                ]
            ]
        ]);
    }

    /**
     * Get employee payroll information.
     */
    public function payroll(Employee $employee, Request $request): JsonResponse
    {
        $query = $employee->payslips();

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('payment_date', $request->year);
        }

        // Filter by month
        if ($request->has('month')) {
            $query->whereMonth('payment_date', $request->month);
        }

        $payslips = $query->orderBy('payment_date', 'desc')
                         ->paginate($request->get('per_page', 12));

        return response()->json([
            'status' => true,
            'message' => 'Employee payroll retrieved successfully',
            'data' => [
                'payslips' => $payslips,
                'current_salary' => $employee->salary,
                'salary_type' => $employee->salary_type,
                'total_earned' => $payslips->sum('net_salary'),
                'last_payslip' => $employee->payslips()->latest('payment_date')->first(),
            ]
        ]);
    }

    /**
     * Get dashboard data for authenticated employee.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $today = Carbon::today();
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;

        // Today's attendance
        $todayAttendance = $employee->attendances()
                                   ->whereDate('date', $today)
                                   ->first();

        // This month attendance summary
        $monthlyAttendance = $employee->attendances()
                                      ->whereMonth('date', $thisMonth)
                                      ->whereYear('date', $thisYear);

        // Leave balance
        $leaveBalance = [
            'total' => $employee->leave_balance ?? 0,
            'used' => $employee->leaves()
                              ->where('status', 'Approved')
                              ->whereYear('created_at', $thisYear)
                              ->sum('total_leave_days'),
            'pending' => $employee->leaves()
                                 ->where('status', 'Pending')
                                 ->sum('total_leave_days'),
        ];

        // Recent activities
        $recentAttendances = $employee->attendances()
                                     ->orderBy('date', 'desc')
                                     ->take(5)
                                     ->get();

        $recentLeaves = $employee->leaves()
                                 ->orderBy('created_at', 'desc')
                                 ->take(3)
                                 ->get();

        // Upcoming holidays (mock data - should come from holidays table)
        $upcomingHolidays = [];

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'employee' => $employee->load(['department', 'designation']),
                'today_attendance' => $todayAttendance,
                'monthly_summary' => [
                    'present_days' => $monthlyAttendance->where('status', 'Present')->count(),
                    'absent_days' => $monthlyAttendance->where('status', 'Absent')->count(),
                    'late_days' => $monthlyAttendance->where('late', 'Yes')->count(),
//                    'half_days' => $monthlyAttendance->where('half_day', 'Yes')->count(),
                ],
                'leave_balance' => $leaveBalance,
                'recent_activities' => [
                    'attendances' => $recentAttendances,
                    'leaves' => $recentLeaves,
                ],
                'upcoming_holidays' => $upcomingHolidays,
                'quick_actions' => [
                    'can_check_in' => !$todayAttendance || !$todayAttendance->clock_in,
                    'can_check_out' => $todayAttendance && $todayAttendance->clock_in && !$todayAttendance->clock_out,
//                    'can_apply_leave' => $leaveBalance['available'] > 0,
                ]
            ]
        ]);
    }

    /**
     * Get dashboard statistics for admin.
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $totalEmployees = Employee::active()->count();
        $totalDepartments = Department::count();
        $presentToday = AttendanceEmployee::whereDate('date', Carbon::today())
                                         ->where('status', 'Present')
                                         ->count();
        $absentToday = AttendanceEmployee::whereDate('date', Carbon::today())
                                        ->where('status', 'Absent')
                                        ->count();
        $pendingLeaves = Leave::where('status', 'Pending')->count();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard statistics retrieved successfully',
            'data' => [
                'total_employees' => $totalEmployees,
                'total_departments' => $totalDepartments,
                'present_today' => $presentToday,
                'absent_today' => $absentToday,
                'pending_leaves' => $pendingLeaves,
                'attendance_rate' => $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 2) : 0,
            ]
        ]);
    }
}
