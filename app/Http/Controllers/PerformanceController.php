<?php

namespace App\Http\Controllers;

use App\Models\Appraisal;
use App\Models\GoalTracking;
use App\Models\PerformanceType;
use App\Models\Indicator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PerformanceController extends Controller
{
    /**
     * Get performance reviews for authenticated employee
     */
    public function reviews(Request $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $query = $employee->appraisals()->with(['appraisalType', 'indicator']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('created_at', $request->year);
        }

        $reviews = $query->orderBy('created_at', 'desc')
                        ->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => true,
            'message' => 'Performance reviews retrieved successfully',
            'data' => [
                'reviews' => $reviews,
                'summary' => [
                    'total_reviews' => $reviews->count(),
                    'completed_reviews' => $employee->appraisals()->where('status', 'Completed')->count(),
                    'pending_reviews' => $employee->appraisals()->where('status', 'Pending')->count(),
                    'average_rating' => $employee->appraisals()->where('status', 'Completed')->avg('rating') ?? 0,
                ]
            ]
        ]);
    }

    /**
     * Get goals for authenticated employee
     */
    public function goals(Request $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $query = GoalTracking::with(['goalType', 'employee', 'createdBy']);

        // For employees, only show their goals
//        if (!Auth::user()->isAdmin()) {
//            $query->where('employee_id', $employee->id);
//        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by goal type
        if ($request->has('goal_type_id')) {
            $query->where('goal_type_id', $request->goal_type_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        $goals = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Goals retrieved successfully',
            'data' => [
                'goals' => $goals,
                'summary' => [
                    'total_goals' => $goals->count(),
                    'completed_goals' => $goals->where('status', 'Completed')->count(),
                    'in_progress_goals' => $goals->where('status', 'In Progress')->count(),
                    'not_started_goals' => $goals->where('status', 'Not Started')->count(),
                ]
            ]
        ]);
    }

    /**
     * Store a new goal
     */
    public function storeGoal(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'goal_type_id' => 'required|exists:goal_types,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'target_value' => 'nullable|numeric',
            'current_value' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'priority' => 'required|in:Low,Medium,High',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Auth::user()->employee;

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee profile not found'
                ], 404);
            }

            $goal = GoalTracking::create([
                'employee_id' => $employee->id,
                'goal_type_id' => $request->goal_type_id,
                'subject' => $request->subject,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'target_value' => $request->target_value,
                'current_value' => $request->current_value ?? 0,
                'unit' => $request->unit,
                'priority' => $request->priority,
                'status' => 'Not Started',
                'progress_percentage' => $this->calculateProgress($request->current_value ?? 0, $request->target_value),
                'created_by' => Auth::id(),
            ]);

            $goal->load(['goalType', 'employee']);

            return response()->json([
                'status' => true,
                'message' => 'Goal created successfully',
                'data' => [
                    'goal' => $goal
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update goal progress
     */
    public function updateGoal(Request $request, GoalTracking $goal): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->isAdmin() && $goal->employee_id !== Auth::user()->employee->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized to update this goal'
            ], 403);
        }

        $validator = validator($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'current_value' => 'nullable|numeric',
            'status' => 'required|in:Not Started,In Progress,Completed,On Hold,Cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $progressPercentage = $this->calculateProgress($request->current_value ?? $goal->current_value, $goal->target_value);

            // Auto-complete if target reached
            if ($progressPercentage >= 100 && $goal->target_value > 0) {
                $request->merge(['status' => 'Completed']);
            }

            $goal->update([
                'subject' => $request->subject,
                'description' => $request->description,
                'current_value' => $request->current_value,
                'status' => $request->status,
                'progress_percentage' => $progressPercentage,
                'notes' => $request->notes,
            ]);

            $goal->load(['goalType', 'employee']);

            return response()->json([
                'status' => true,
                'message' => 'Goal updated successfully',
                'data' => [
                    'goal' => $goal
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance indicators
     */
    public function indicators(Request $request): JsonResponse
    {
        $query = Indicator::with(['performanceType']);

        // Filter by performance type
        if ($request->has('performance_type_id')) {
            $query->where('performance_type_id', $request->performance_type_id);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $indicators = $query->orderBy('name')
                           ->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => true,
            'message' => 'Performance indicators retrieved successfully',
            'data' => [
                'indicators' => $indicators
            ]
        ]);
    }

    /**
     * Get performance summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        $thisYear = Carbon::now()->year;

        // Goals summary
        $goalsSummary = [
            'total_goals' => $employee->goalTrackings()->count(),
            'completed_this_year' => $employee->goalTrackings()
                                             ->where('status', 'Completed')
                                             ->whereYear('end_date', $thisYear)
                                             ->count(),
            'in_progress' => $employee->goalTrackings()->where('status', 'In Progress')->count(),
            'overdue' => $employee->goalTrackings()
                                ->where('status', '!=', 'Completed')
                                ->where('end_date', '<', now())
                                ->count(),
        ];

        // Appraisals summary
        $appraisalsSummary = [
            'total_appraisals' => $employee->appraisals()->count(),
            'latest_rating' => $employee->appraisals()->latest()->first()->rating ?? 0,
            'average_rating' => $employee->appraisals()->where('status', 'Completed')->avg('rating') ?? 0,
            'last_appraisal_date' => $employee->appraisals()->latest()->first()->created_at ?? null,
        ];

        // Recent goals
        $recentGoals = $employee->goalTrackings()
                              ->with('goalType')
                              ->orderBy('created_at', 'desc')
                              ->take(5)
                              ->get();

        // Upcoming goals (ending soon)
        $upcomingGoals = $employee->goalTrackings()
                                ->where('status', '!=', 'Completed')
                                ->whereBetween('end_date', [now(), now()->addDays(30)])
                                ->with('goalType')
                                ->orderBy('end_date')
                                ->take(3)
                                ->get();

        return response()->json([
            'status' => true,
            'message' => 'Performance summary retrieved successfully',
            'data' => [
                'goals_summary' => $goalsSummary,
                'appraisals_summary' => $appraisalsSummary,
                'recent_goals' => $recentGoals,
                'upcoming_goals' => $upcomingGoals,
                'achievements' => [
                    'goals_completed_this_year' => $goalsSummary['completed_this_year'],
                    'completion_rate' => $goalsSummary['total_goals'] > 0 ?
                        round(($goalsSummary['completed_this_year'] / $goalsSummary['total_goals']) * 100, 2) : 0,
                ],
                'quick_actions' => [
                    'can_create_goal' => true,
                    'has_pending_appraisal' => $employee->appraisals()->where('status', 'Pending')->exists(),
                    'has_overdue_goals' => $goalsSummary['overdue'] > 0,
                ]
            ]
        ]);
    }

    /**
     * Get performance types
     */
    public function performanceTypes(Request $request): JsonResponse
    {
        $types = PerformanceType::where('is_active', true)
                              ->orderBy('name')
                              ->get();

        return response()->json([
            'status' => true,
            'message' => 'Performance types retrieved successfully',
            'data' => [
                'performance_types' => $types
            ]
        ]);
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress($currentValue, $targetValue): float
    {
        if (!$targetValue || $targetValue <= 0) {
            return 0;
        }

        $progress = ($currentValue / $targetValue) * 100;
        return min(100, max(0, round($progress, 2)));
    }

    /**
     * Delete goal
     */
    public function destroyGoal(Request $request, GoalTracking $goal): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->isAdmin() && $goal->employee_id !== Auth::user()->employee->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized to delete this goal'
            ], 403);
        }

        try {
            $goal->delete();

            return response()->json([
                'status' => true,
                'message' => 'Goal deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete goal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
