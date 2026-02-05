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

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->has('month')) {
            $query->whereMonth('date', $request->month);
        }
        if ($request->has('year')) {
            $query->whereYear('date', $request->year);
        }


        $attendances = $query->orderBy('date', 'desc')
            ->paginate($request->get('per_page', 20));


        $attendances->getCollection()->transform(function ($attendance) {

            $attendance->total_time = 'N/A';

            if ($attendance->clock_in && $attendance->clock_out) {
                try {

                    $start = Carbon::parse($attendance->clock_in);
                    $end = Carbon::parse($attendance->clock_out);


                    $diff = $start->diff($end);

                    $parts = [];
                    if ($diff->h > 0) $parts[] = "{$diff->h}h";
                    if ($diff->i > 0) $parts[] = "{$diff->i}m";
                    $parts[] = "{$diff->s}s";

                    $attendance->total_time = implode(' ', $parts);
                } catch (\Exception $e) {

                }
            } elseif ($attendance->clock_in && !$attendance->clock_out) {
                $attendance->total_time = 'Active';
            }

            return $attendance;
        });

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
        // 1. UPDATE VALIDATION
        $validator = Validator::make($request->all(), [
            'status'   => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255', // Validate location
            'image'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Validate image (max 5MB)
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

            // Shift Logic
            $shiftStartStr = config('attendance.shift_start', '09:00:00');
            [$h, $m, $s] = array_map('intval', explode(':', $shiftStartStr));
            $shiftStart = (clone $now)->setTime($h, $m, $s);

            $late = '00:00:00';
            if ($now->gt($shiftStart)) {
                $diffSeconds = $shiftStart->diffInSeconds($now);
                $late = gmdate('H:i:s', $diffSeconds);
            }

            $attendance = AttendanceEmployee::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            $alreadyCheckedIn = $attendance && $attendance->clock_in !== '00:00:00';

            if ($alreadyCheckedIn) {
                return response()->json([
                    'status' => false,
                    'message' => 'Already checked in today'
                ], 400);
            }

            // 2. HANDLE IMAGE UPLOAD
            $imagePath = null;
            if ($request->hasFile('image')) {
                // Stores in storage/app/public/attendance_images
                // Ensure you run: php artisan storage:link
                $imagePath = $request->file('image')->store('attendance_images', 'public');
            }

            // 3. CAPTURE LOCATION
            $location = $request->input('location');

            $clockInTime = $now->format('H:i:s');
            $status = $request->input('status', 'Present');

            if ($attendance) {
                // Update existing record
                $attendance->update([
                    'clock_in' => $clockInTime,
                    'late'     => $late,
                    'status'   => $status,
                    'image'    => $imagePath, // Save image path
                    'location' => $location,  // Save location
                ]);
            } else {
                // Create fresh record
                $attendance = AttendanceEmployee::create([
                    'employee_id'   => $employee->id,
                    'date'          => $today->format('Y-m-d'),
                    'status'        => $status,
                    'clock_in'      => $clockInTime,
                    'clock_out'     => '00:00:00',
                    'late'          => $late,
                    'early_leaving' => '00:00:00',
                    'overtime'      => '00:00:00',
                    'total_rest'    => '00:00:00',
                    'created_by'    => Auth::id(),
                    'image'         => $imagePath, // Save image path
                    'location'      => $location,  // Save location
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Checked in successfully',
                'data' => [
                    'attendance' => $attendance->load('employee'),
                    'check_in_time' => $clockInTime,
                    'late' => $late,
                    'image_url' => $imagePath ? asset('storage/' . $imagePath) : null, // Return full URL
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
            $employee = $request->user()->employee;

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

            // Check if checked in (assuming clock_in is string "00:00:00" when empty)
            if ($attendance->clock_in === '00:00:00') {
                return response()->json([
                    'status' => false,
                    'message' => 'Please check in first'
                ], 400);
            }

            $clockOutTime = $now->format('H:i:s');

            // Shift end time setup
            $shiftEndStr = config('attendance.shift_end', '17:00:00');
            [$h, $m, $s] = array_map('intval', explode(':', $shiftEndStr));
            $shiftEnd = (clone $now)->setTime($h, $m, $s);

            // Calculate early leaving
            $earlyLeaving = '00:00:00';
            if ($now->lt($shiftEnd)) {
                $diffSeconds = $now->diffInSeconds($shiftEnd);
                $earlyLeaving = gmdate('H:i:s', $diffSeconds);
            }

            // --- FIX START ---
            $clockInCarbon = Carbon::parse($attendance->clock_in);

            $clockInCarbon->setDate($now->year, $now->month, $now->day);

            $clockOutCarbon = Carbon::parse($clockOutTime);
            $clockOutCarbon->setDate($now->year, $now->month, $now->day);
            // --- FIX END ---

            // Handle cases where clock_out is next day (past midnight relative to clock_in)
            if ($clockOutCarbon->lt($clockInCarbon)) {
                $clockOutCarbon->addDay();
            }

            $workingSeconds = $clockInCarbon->diffInSeconds($clockOutCarbon);

            // Subtract total_rest
            $restSeconds = 0;
            if ($attendance->total_rest && $attendance->total_rest !== '00:00:00') {
                // Check if total_rest contains a ":" before exploding to prevent errors
                if (strpos($attendance->total_rest, ':') !== false) {
                    [$rh, $rm, $rs] = array_map('intval', explode(':', $attendance->total_rest));
                    $restSeconds = ($rh * 3600) + ($rm * 60) + $rs;
                }
            }

            $netWorkingSeconds = max(0, $workingSeconds - $restSeconds);
            $totalWork = gmdate('H:i:s', $netWorkingSeconds);

            // Calculate overtime
            $overtime = '00:00:00';
            $standardWorkSeconds = 8 * 3600;

            if ($netWorkingSeconds > $standardWorkSeconds) {
                $overtimeSeconds = $netWorkingSeconds - $standardWorkSeconds;
                $overtime = gmdate('H:i:s', $overtimeSeconds);
            }

            // Update attendance record
            $attendance->update([
                'clock_out' => $clockOutTime,
                'early_leaving' => $earlyLeaving,
                'overtime' => $overtime,
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


    public function requestOvertime(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'hours' => 'required|integer|min:1',
            'validity_period' => 'required|string',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $employee = $user->employee;

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee profile not found for this user.'
                ], 404);
            }

             $days = (int) filter_var($request->validity_period, FILTER_SANITIZE_NUMBER_INT);
            if ($days <= 0) {
                $days = 1;
            }


            $overtime = new \App\Models\Overtime();
            $overtime->employee_id = $employee->id;
            $overtime->title = $request->reason;
            $overtime->hours = $request->hours;
            $overtime->number_of_days = $days;
            $overtime->rate = 0.0;
            $overtime->type = $request->validity_period;
            $overtime->status = 'pending';
            $overtime->created_by = $user->id;
            $overtime->save();

            return response()->json([
                'status' => true,
                'message' => 'Overtime request submitted successfully.',
                'data' => $overtime
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit overtime request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOvertimeHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $employee = $user->employee;

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee record not found'], 404);
            }

            $query = \App\Models\Overtime::where('employee_id', $employee->id);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            $overtimes = $query->latest()
                ->paginate($request->get('per_page', 10));

            $overtimes->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'date' => $item->created_at->format('d/m/y'),
                    'validity_period' => $item->number_of_days . ' Day(s)',
                    'hours' => $item->hours . ' Hours',
                    'status' => ucfirst($item->status),
                    'raw_status' => $item->status,
                    'reason' => $item->title,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Overtime history retrieved successfully',
                'data' => $overtimes
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch overtime history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOvertimeChartData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $employee = $user->employee;

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found'], 404);
            }

            $totalRequests = \App\Models\Overtime::where('employee_id', $employee->id)->count();

            $stats = \App\Models\Overtime::where('employee_id', $employee->id)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $approvedCount = $stats['approved'] ?? 0;
            $deniedCount   = $stats['declined'] ?? 0;
            $pendingCount  = $stats['pending'] ?? 0;

            $percentages = [
                'approved' => $totalRequests > 0 ? round(($approvedCount / $totalRequests) * 100) : 0,
                'denied'   => $totalRequests > 0 ? round(($deniedCount / $totalRequests) * 100) : 0,
                'in_review'=> $totalRequests > 0 ? round(($pendingCount / $totalRequests) * 100) : 0,
            ];

            $chartData = [];
            $months = collect([]);


            for ($i = 3; $i >= 0; $i--) {
                $months->push(Carbon::now()->subMonths($i));
            }

            foreach ($months as $month) {
                $monthName = $month->format('M'); // e.g., "Jan", "Feb"
                $monthNum = $month->month;
                $year = $month->year;

                // Fetch counts for this specific month
                $monthlyStats = \App\Models\Overtime::where('employee_id', $employee->id)
                    ->whereMonth('created_at', $monthNum)
                    ->whereYear('created_at', $year)
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                $chartData[] = [
                    'month'    => $monthName,
                    'approved' => $monthlyStats['approved'] ?? 0,
                    'denied'   => $monthlyStats['declined'] ?? 0,
                    'pending'  => $monthlyStats['pending'] ?? 0,
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Chart data retrieved successfully',
                'data' => [
                    'percentages' => $percentages,
                    'chart' => $chartData
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
