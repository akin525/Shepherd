<?php

namespace App\Http\Controllers\Operations;

use App\Models\AttendanceEmployee;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\AuditLogService;

class DashboardController
{

    public function getDashboardData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
//            $client_id = $user->client->id;


            $activeGuardsCount = \App\Models\Employee::where('is_active', true)
                ->count();

            $incidentsToday = \App\Models\Complaint::whereDate('created_at', Carbon::today())
                ->count();


            $assignedLocationsCount = \App\Models\Branch::count();


            $chartData = [];
            $days = collect([]);

            for ($i = 6; $i >= 0; $i--) {
                $days->push(Carbon::now()->subDays($i));
            }

            foreach ($days as $day) {
                $totalActive = $activeGuardsCount;

                $presentCount = \App\Models\AttendanceEmployee::whereDate('date', $day->format('Y-m-d'))
                    ->distinct('employee_id')
                    ->count();

                $rate = $totalActive > 0 ? round(($presentCount / $totalActive) * 100) : 0;

                $chartData[] = [
                    'day' => $day->format('D'), // Mon, Tue...
                    'rate' => $rate,
                    'present' => $presentCount,
                    'total' => $totalActive
                ];
            }

            $activities = collect();

            $attendances = \App\Models\AttendanceEmployee::with('employee')
                ->latest('created_at')
                ->take(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => 'att-' . $item->id,
                        'description' => "Attendance recorded for " . ($item->employee->name ?? 'Guard'),
                        'time' => Carbon::parse($item->clock_in ?? $item->created_at)->format('g:i A'),
                        'timestamp' => $item->created_at,
                        'type' => 'attendance'
                    ];
                });
            $activities = $activities->merge($attendances);

            $incidents = \App\Models\Complaint::latest('created_at')
                ->take(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => 'inc-' . $item->id,
                        'description' => "Incident reported: " . Str::limit($item->title, 25),
                        'time' => $item->created_at->format('g:i A'),
                        'timestamp' => $item->created_at,
                        'type' => 'incident'
                    ];
                });
            $activities = $activities->merge($incidents);

            $recentActivities = $activities->sortByDesc('timestamp')->take(6)->values();


            // Log dashboard view
            AuditLogService::logView(
                $user,
                'Operations Dashboard',
                'Dashboard Data',
                "Viewed operations dashboard with {$activeGuardsCount} active guards and {$incidentsToday} incidents today"
            );

            return response()->json([
                'status' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => [
                    'stats' => [
                        'assigned_locations' => $assignedLocationsCount,
                        'active_guards' => $activeGuardsCount,
                        'incidents_today' => $incidentsToday
                    ],
                    'attendance_chart' => $chartData,
                    'recent_activities' => $recentActivities
                ]
            ]);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                $user ?? null,
                'Operations Dashboard',
                'View Dashboard',
                'Failed to load dashboard: ' . $e->getMessage()
            );
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to load dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAttendanceOverview(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $today = Carbon::today()->format('Y-m-d');

            $totalScheduled = Employee::where('is_active', true)->count();
            $todaysAttendance = AttendanceEmployee::whereDate('date', $today)->get();
            $onDutyCount = $todaysAttendance->whereNull('clock_out')->count();
            $lateCount = $todaysAttendance->filter(function($att) {
                return Carbon::parse($att->clock_in)->gt(Carbon::parse('08:00:00'));
            })->count();

            $presentCount = $todaysAttendance->count();
            $absentCount = max(0, $totalScheduled - $presentCount);

            $query = Employee::with(['branch', 'attendance' => function($q) use ($today) {
                $q->whereDate('date', $today);
            }])->where('is_active', true);

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('branch', function($b) use ($search) {
                            $b->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $employees = $query->paginate($request->get('per_page', 10));

            $employees->getCollection()->transform(function ($employee) {
                $attendance = $employee->attendance->first();

                $status = 'Absent';
                $clockIn = '---';
                $clockOut = '---';
                $statusColor = 'text-red-500';

                if ($attendance) {
                    $clockIn = Carbon::parse($attendance->clock_in)->format('h:i A');
                    $clockOut = $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('h:i A') : '---';

                    if ($attendance->clock_out) {
                        $status = 'Completed';
                        $statusColor = 'text-green-600';
                    } elseif ($attendance->clock_in) {
                        $isLate = Carbon::parse($attendance->clock_in)->gt(Carbon::parse('08:00:00'));

                        if ($isLate) {
                            $status = 'Late';
                            $statusColor = 'text-orange-500';
                        } else {
                            $status = 'On Duty';
                            $statusColor = 'text-blue-600';
                        }
                    }
                }

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'location' => $employee->branch->name ?? 'Unassigned',
                    'shift' => $employee->shift ?? 'Day Shift',
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'status' => $status,
                    'status_color' => $statusColor,
                    'avatar' => $employee->profile_picture ?? null,
                ];
            });

            // Log attendance overview view
            AuditLogService::logView(
                $user,
                'Operations Dashboard',
                'Attendance Overview',
                "Viewed attendance overview: {$onDutyCount} on duty, {$lateCount} late, {$absentCount} absent"
            );

            return response()->json([
                'status' => true,
                'message' => 'Attendance overview retrieved successfully',
                'data' => [
                    'stats' => [
                        'guards_scheduled' => $totalScheduled,
                        'guards_on_duty' => $onDutyCount,
                        'late_clock_ins' => $lateCount,
                        'absent_guards' => $absentCount
                    ],
                    'attendance_list' => $employees
                ]
            ]);

        } catch (\Throwable $e) {
            // Log failure
            AuditLogService::logFailure(
                Auth::user() ?? null,
                'Operations Dashboard',
                'Attendance Overview',
                'Failed to fetch attendance data: ' . $e->getMessage()
            );
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch attendance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
