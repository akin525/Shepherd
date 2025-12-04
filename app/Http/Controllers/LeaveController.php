<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    /**
     * Display a listing of leaves.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Leave::with(['employee', 'leaveType', 'approver']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by leave type
        if ($request->has('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        // Filter by current user (employee viewing their own leaves)
        if ($request->has('my_leaves') && $request->boolean('my_leaves')) {
            $employee = $request->user()->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
        }

        $leaves = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => true,
            'message' => 'Leaves retrieved successfully',
            'data' => [
                'leaves' => $leaves
            ]
        ]);
    }

    /**
     * Store a newly created leave request.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
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

            // Calculate leave days
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;

            // Check for overlapping leaves
            $overlappingLeave = Leave::where('employee_id', $employee->id)
                                    ->where('status', '!=', 'Reject')
                                    ->where(function ($query) use ($request) {
                                        $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                                              ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                                              ->orWhere(function ($q) use ($request) {
                                                  $q->where('start_date', '<=', $request->start_date)
                                                    ->where('end_date', '>=', $request->end_date);
                                              });
                                    })
                                    ->first();

            if ($overlappingLeave) {
                return response()->json([
                    'status' => false,
                    'message' => 'You already have a leave request for this period'
                ], 400);
            }

            // Handle file upload
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $attachmentPath = $file->storeAs('leave_attachments', $fileName, 'public');
            }

            $leave = Leave::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_leave_days' => $totalDays,
                'reason' => $request->reason,
                'attachment' => $attachmentPath,
                'status' => 'Pending',
                'applied_on' => Carbon::now()->format('Y-m-d'),
                'created_by' => Auth::id(),
            ]);

            $leave->load(['employee', 'leaveType']);

            return response()->json([
                'status' => true,
                'message' => 'Leave request submitted successfully',
                'data' => [
                    'leave' => $leave
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified leave.
     */
    public function show(Leave $leave): JsonResponse
    {
        $leave->load(['employee', 'leaveType', 'approver']);

        return response()->json([
            'status' => true,
            'message' => 'Leave retrieved successfully',
            'data' => [
                'leave' => $leave
            ]
        ]);
    }

    /**
     * Update the specified leave request.
     */
    public function update(Request $request, Leave $leave): JsonResponse
    {
        // Check if user can update this leave
        if ($leave->employee_id !== $request->user()->employee->id && !$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized to update this leave request'
            ], 403);
        }

        // Only allow updates if leave is still pending
        if ($leave->status !== 'Pending') {
            return response()->json([
                'status' => false,
                'message' => 'Cannot update leave request that has been processed'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Calculate leave days
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;

            // Handle file upload
            $attachmentPath = $leave->attachment;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $attachmentPath = $file->storeAs('leave_attachments', $fileName, 'public');
            }

            $leave->update([
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_leave_days' => $totalDays,
                'reason' => $request->reason,
                'attachment' => $attachmentPath,
            ]);

            $leave->load(['employee', 'leaveType']);

            return response()->json([
                'status' => true,
                'message' => 'Leave request updated successfully',
                'data' => [
                    'leave' => $leave
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified leave request.
     */
    public function destroy(Leave $leave): JsonResponse
    {
        // Check if user can delete this leave
        if ($leave->employee_id !== $request->user()->employee->id && !$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized to delete this leave request'
            ], 403);
        }

        // Only allow deletion if leave is still pending
        if ($leave->status !== 'Pending') {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete leave request that has been processed'
            ], 400);
        }

        try {
            $leave->delete();

            return response()->json([
                'status' => true,
                'message' => 'Leave request deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve leave request (Admin/HR only)
     */
    public function approve(Request $request, Leave $leave): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'approval_note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($leave->status !== 'Pending') {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave request has already been processed'
                ], 400);
            }

            $leave->update([
                'status' => 'Approved',
                'approved_by' => Auth::id(),
                'approval_date' => Carbon::now(),
                'approval_note' => $request->approval_note,
            ]);

            $leave->load(['employee', 'leaveType', 'approver']);

            return response()->json([
                'status' => true,
                'message' => 'Leave request approved successfully',
                'data' => [
                    'leave' => $leave
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to approve leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject leave request (Admin/HR only)
     */
    public function reject(Request $request, Leave $leave): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($leave->status !== 'Pending') {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave request has already been processed'
                ], 400);
            }

            $leave->update([
                'status' => 'Reject',
                'approved_by' => Auth::id(),
                'approval_date' => Carbon::now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            $leave->load(['employee', 'leaveType', 'approver']);

            return response()->json([
                'status' => true,
                'message' => 'Leave request rejected successfully',
                'data' => [
                    'leave' => $leave
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to reject leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get leave types
     */
    public function leaveTypes(): JsonResponse
    {
        $leaveTypes = LeaveType::orderBy('title')
                              ->get();

        return response()->json([
            'status' => true,
            'message' => 'Leave types retrieved successfully',
            'data' => [
                'leave_types' => $leaveTypes
            ]
        ]);
    }

    /**
     * Get leave balance for authenticated employee
     */
    public function leaveBalance(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee profile not found'
            ], 404);
        }

        // Get all leave types
        $leaveTypes = LeaveType::get();

        // Calculate leave balance for each type
        $leaveBalance = $leaveTypes->map(function ($leaveType) use ($employee) {
            $totalAllocated = $leaveType->days ?? 0;

            $usedDays = Leave::where('employee_id', $employee->id)
                           ->where('leave_type_id', $leaveType->id)
                           ->where('status', 'Approved')
                           ->whereYear('start_date', Carbon::now()->year)
                           ->sum('total_leave_days');

            $pendingDays = Leave::where('employee_id', $employee->id)
                              ->where('leave_type_id', $leaveType->id)
                              ->where('status', 'Pending')
                              ->sum('total_leave_days');

            return [
                'leave_type' => $leaveType,
                'total_allocated' => $totalAllocated,
                'used_days' => $usedDays,
                'pending_days' => $pendingDays,
                'available_days' => max(0, $totalAllocated - $usedDays - $pendingDays),
                'usage_percentage' => $totalAllocated > 0 ? round(($usedDays / $totalAllocated) * 100, 2) : 0,
            ];
        });

        // Get overall leave statistics
        $totalLeavesThisYear = Leave::where('employee_id', $employee->id)
                                  ->where('status', 'Approved')
                                  ->whereYear('start_date', Carbon::now()->year)
                                  ->sum('total_leave_days');

        $pendingLeaves = Leave::where('employee_id', $employee->id)
                             ->where('status', 'Pending')
                             ->sum('total_leave_days');

        $recentLeaves = Leave::where('employee_id', $employee->id)
                            ->orderBy('created_at', 'desc')
                            ->take(5)
                            ->with(['leaveType'])
                            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Leave balance retrieved successfully',
            'data' => [
                'leave_balance' => $leaveBalance,
                'summary' => [
                    'total_used_this_year' => $totalLeavesThisYear,
                    'pending_requests' => $pendingLeaves,
                    'total_available' => $leaveBalance->sum('available_days'),
                ],
                'recent_leaves' => $recentLeaves,
            ]
        ]);
    }
}
