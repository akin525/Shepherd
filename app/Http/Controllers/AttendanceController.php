<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEmployee;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendances.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceEmployee::with(['employee', 'employee.department', 'employee.designation']);

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by department
        if ($request->has('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        // Filter by month/year
        if ($request->has('month')) {
            $query->whereMonth('date', $request->month);
        }
        if ($request->has('year')) {
            $query->whereYear('date', $request->year);
        }

        $attendances = $query->orderBy('date', 'desc')
                            ->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => true,
            'message' => 'Attendances retrieved successfully',
            'data' => [
                'attendances' => $attendances
            ]
        ]);
    }

    /**
     * Check in employee
     */
    public function checkIn(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = $request->user()->employee;

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee profile not found'
                ], 404);
            }

            $today = Carbon::today();
            $now = Carbon::now();

            // Shift start time (HH:MM:SS). Adjust via config/env as needed.
            $shiftStartStr = config('attendance.shift_start', '09:00:00');
            [$h, $m, $s] = array_map('intval', explode(':', $shiftStartStr));
            $shiftStart = (clone $now)->setTime($h, $m, $s);

            // Compute late duration (time type) as HH:MM:SS
            $late = '00:00:00';
            if ($now->gt($shiftStart)) {
                $diffSeconds = $shiftStart->diffInSeconds($now);
                $late = gmdate('H:i:s', $diffSeconds);
            }

            // Find today's attendance
            $attendance = AttendanceEmployee::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            // Because clock_in is NOT NULL in the schema, a not-checked-in record may still hold "00:00:00".
            $alreadyCheckedIn = $attendance && $attendance->clock_in !== '00:00:00';

            if ($alreadyCheckedIn) {
                return response()->json([
                    'status' => false,
                    'message' => 'Already checked in today'
                ], 400);
            }

            $clockInTime = $now->format('H:i:s');
            $status = $request->input('status', 'Present');

            if ($attendance) {
                // Update existing record (assume "00:00:00" means not checked in yet)
                $attendance->update([
                    'clock_in'       => $clockInTime,
                    'late'           => $late,
                    'status'         => $status,
                    // Keep clock_out/early_leaving/overtime/total_rest as they are (to be set at check-out/end-of-day)
                ]);
            } else {
                // Create a fresh record. Several TIME columns are NOT NULL, set to "00:00:00" by default.
                $attendance = AttendanceEmployee::create([
                    'employee_id'     => $employee->id,
                    'date'            => $today->format('Y-m-d'),
                    'status'          => $status,
                    'clock_in'        => $clockInTime,
                    'clock_out'       => '00:00:00',
                    'late'            => $late,
                    'early_leaving'   => '00:00:00',
                    'overtime'        => '00:00:00',
                    'total_rest'      => '00:00:00',
                    'created_by'      => Auth::id(),
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Checked in successfully',
                'data' => [
                    'attendance' => $attendance->load('employee'),
                    'check_in_time' => $clockInTime,
                    'late' => $late,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to check in',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Check out employee
     */
    public function checkOut(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            // No specific fields required for check-out based on your schema
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee =$request->user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $today = Carbon::today();
        $now = Carbon::now();

        // Get today's attendance
        $attendance = AttendanceEmployee::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => false,
                'message' => 'No attendance record found for today'
            ], 404);
        }

        // Check if already checked in (clock_in should not be "00:00:00")
        if ($attendance->clock_in === '00:00:00') {
            return response()->json([
                'status' => false,
                'message' => 'Please check in first'
            ], 400);
        }

        // Check if already checked out
        if ($attendance->clock_out !== '00:00:00') {
            return response()->json([
                'status' => false,
                'message' => 'Already checked out today'
            ], 400);
        }

        $clockOutTime =$now->format('H:i:s');

        // Shift end time (HH:MM:SS). Adjust via config/env as needed.
        $shiftEndStr = config('attendance.shift_end', '17:00:00');
        [$h,$m, $s] = array_map('intval', explode(':', $shiftEndStr));
        $shiftEnd = (clone$now)->setTime($h,$m, $s);

        // Calculate early leaving duration (TIME type) as HH:MM:SS
        $earlyLeaving = '00:00:00';
        if ($now->lt($shiftEnd)) {
            $diffSeconds =$now->diffInSeconds($shiftEnd);
            $earlyLeaving = gmdate('H:i:s', $diffSeconds);
        }

        // Calculate total work duration
        $clockInCarbon = Carbon::createFromFormat('H:i:s', $attendance->clock_in);
        $clockOutCarbon = Carbon::createFromFormat('H:i:s', $clockOutTime);

        // Handle cases where clock_out is next day (past midnight)
        if ($clockOutCarbon->lt($clockInCarbon)) {
            $clockOutCarbon->addDay();
        }

        $workingSeconds =$clockInCarbon->diffInSeconds($clockOutCarbon);

        // Subtract total_rest (break time) if any
        $restSeconds = 0;
        if ($attendance->total_rest && $attendance->total_rest !== '00:00:00') {
            [$rh,$rm, $rs] = array_map('intval', explode(':', $attendance->total_rest));
            $restSeconds = ($rh * 3600) + ($rm * 60) +$rs;
        }

        $netWorkingSeconds = max(0,$workingSeconds - $restSeconds);
        $totalWork = gmdate('H:i:s', $netWorkingSeconds);

        // Calculate overtime (time worked beyond shift end)
        $overtime = '00:00:00';
        $standardWorkSeconds = 8 * 3600; // 8 hours standard shift (configurable)

        if ($netWorkingSeconds > $standardWorkSeconds) {
            $overtimeSeconds =$netWorkingSeconds - $standardWorkSeconds;
            $overtime = gmdate('H:i:s', $overtimeSeconds);
        }

        // Update attendance record
        $attendance->update([
            'clock_out'      => $clockOutTime,
            'early_leaving'  => $earlyLeaving,
            'overtime'       => $overtime,
            // total_rest remains as is (should be updated by break tracking logic)
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Checked out successfully',
            'data' => [
                'attendance' => $attendance->load('employee'),
                'check_out_time' => $clockOutTime,
                'total_work' => $totalWork,
                'early_leaving' => $earlyLeaving,
                'overtime' => $overtime,
                'left_early' => $earlyLeaving !== '00:00:00',
            ]
        ], 200);

    } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to check out',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary
     */
    public function summary(Request $request): JsonResponse
    {
        $employeeId = $request->get('employee_id');
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $query = AttendanceEmployee::whereMonth('date', $month)
                                  ->whereYear('date', $year);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $attendances = $query->get();

        $summary = [
            'total_days' => $attendances->count(),
            'present_days' => $attendances->where('status', 'Present')->count(),
            'absent_days' => $attendances->where('status', 'Absent')->count(),
            'late_days' => $attendances->where('late', 'Yes')->count(),
            'half_days' => $attendances->where('half_day', 'Yes')->count(),
            'early_leaving_days' => $attendances->where('early_leaving', 'Yes')->count(),
            'total_work_hours' => $this->calculateTotalWorkHours($attendances),
            'average_work_hours' => $this->calculateAverageWorkHours($attendances),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Attendance summary retrieved successfully',
            'data' => [
                'summary' => $summary,
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'month_name' => Carbon::create($year, $month)->format('F'),
                ],
                'daily_breakdown' => $attendances->map(function ($attendance) {
                    return [
                        'date' => $attendance->date,
                        'status' => $attendance->status,
                        'clock_in' => $attendance->clock_in,
                        'clock_out' => $attendance->clock_out,
                        'total_work' => $attendance->total_work,
                        'late' => $attendance->late,
                        'early_leaving' => $attendance->early_leaving,
                    ];
                })
            ]
        ]);
    }

    /**
     * Get my attendance (authenticated employee)
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $query = $employee->attendances();

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

        // Get today's attendance
        $todayAttendance = $employee->attendances()
                                   ->whereDate('date', Carbon::today())
                                   ->first();

        // Get this month summary
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;
        $monthlyAttendances = $employee->attendances()
                                      ->whereMonth('date', $thisMonth)
                                      ->whereYear('date', $thisYear)
                                      ->get();

        $monthlySummary = [
            'present_days' => $monthlyAttendances->where('status', 'Present')->count(),
            'absent_days' => $monthlyAttendances->where('status', 'Absent')->count(),
            'late_days' => $monthlyAttendances->where('late', 'Yes')->count(),
            'total_work_hours' => $this->calculateTotalWorkHours($monthlyAttendances),
        ];

        return response()->json([
            'status' => true,
            'message' => 'My attendance retrieved successfully',
            'data' => [
                'attendances' => $attendances,
                'today_attendance' => $todayAttendance,
                'monthly_summary' => $monthlySummary,
                'quick_stats' => [
                    'can_check_in' => !$todayAttendance || !$todayAttendance->clock_in,
                    'can_check_out' => $todayAttendance && $todayAttendance->clock_in && !$todayAttendance->clock_out,
                ]
            ]
        ]);
    }

    /**
     * Adjust attendance (Admin only)
     */
    public function adjustAttendance(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'attendance_id' => 'required|exists:attendance_employees,id',
            'clock_in' => 'nullable|date_format:H:i:s',
            'clock_out' => 'nullable|date_format:H:i:s',
            'status' => 'required|in:Present,Absent,Leave,Holiday',
            'late' => 'nullable|in:Yes,No',
            'half_day' => 'nullable|in:Yes,No',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attendance = AttendanceEmployee::findOrFail($request->attendance_id);

            // Calculate total work hours if both clock in and out are provided
            $totalWork = null;
            if ($request->clock_in && $request->clock_out) {
                $clockIn = Carbon::parse($request->clock_in);
                $clockOut = Carbon::parse($request->clock_out);
                $workingHours = $clockIn->diffInHours($clockOut);
                $workingMinutes = $clockIn->diffInMinutes($clockOut) % 60;
                $totalWork = sprintf('%02d:%02d', $workingHours, $workingMinutes);
            }

            $attendance->update([
                'clock_in' => $request->clock_in,
                'clock_out' => $request->clock_out,
                'status' => $request->status,
                'late' => $request->late,
                'half_day' => $request->half_day,
                'total_work' => $totalWork,
                'notes' => $request->reason,
                'adjusted_by' => Auth::id(),
                'adjusted_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Attendance adjusted successfully',
                'data' => [
                    'attendance' => $attendance->load('employee')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to adjust attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance report (Admin only)
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);
        $departmentId = $request->get('department_id');

        $query = AttendanceEmployee::with(['employee', 'employee.department', 'employee.designation'])
                                   ->whereMonth('date', $month)
                                   ->whereYear('date', $year);

        if ($departmentId) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $attendances = $query->orderBy('date')->get();

        // Group by employee
        $report = $attendances->groupBy('employee_id')->map(function ($employeeAttendances) {
            $employee = $employeeAttendances->first()->employee;

            return [
                'employee' => $employee,
                'summary' => [
                    'present_days' => $employeeAttendances->where('status', 'Present')->count(),
                    'absent_days' => $employeeAttendances->where('status', 'Absent')->count(),
                    'late_days' => $employeeAttendances->where('late', 'Yes')->count(),
                    'half_days' => $employeeAttendances->where('half_day', 'Yes')->count(),
                    'total_work_hours' => $this->calculateTotalWorkHours($employeeAttendances),
                ],
                'daily_records' => $employeeAttendances->map(function ($att) {
                    return [
                        'date' => $att->date,
                        'clock_in' => $att->clock_in,
                        'clock_out' => $att->clock_out,
                        'status' => $att->status,
                        'total_work' => $att->total_work,
                        'late' => $att->late,
                    ];
                })
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Attendance report generated successfully',
            'data' => [
                'report' => $report->values(),
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'month_name' => Carbon::create($year, $month)->format('F'),
                ],
                'overall_summary' => [
                    'total_employees' => $report->count(),
                    'total_present_days' => $report->sum('summary.present_days'),
                    'total_absent_days' => $report->sum('summary.absent_days'),
                    'total_late_days' => $report->sum('summary.late_days'),
                ]
            ]
        ]);
    }

    /**
     * Calculate total work hours from collection of attendances
     */
    private function calculateTotalWorkHours($attendances): string
    {
        $totalMinutes = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->total_work) {
                $timeParts = explode(':', $attendance->total_work);
                if (count($timeParts) === 2) {
                    $totalMinutes += ($timeParts[0] * 60) + $timeParts[1];
                }
            }
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Calculate average work hours from collection of attendances
     */
    private function calculateAverageWorkHours($attendances): string
    {
        $totalWorkHours = $this->calculateTotalWorkHours($attendances);
        $totalMinutes = 0;

        $timeParts = explode(':', $totalWorkHours);
        if (count($timeParts) === 2) {
            $totalMinutes = ($timeParts[0] * 60) + $timeParts[1];
        }

        if ($attendances->count() === 0) {
            return '00:00';
        }

        $averageMinutes = $totalMinutes / $attendances->count();
        $hours = intdiv($averageMinutes, 60);
        $minutes = round($averageMinutes % 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
