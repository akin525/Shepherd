<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLogService;

class ComplaintController extends Controller
{
    /**
     * Display a listing of complaints.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Complaint::with(['client', 'assignedUser', 'creator']);

        // 1. Role-based access control
        if (! $user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only see their own complaints
                $query->whereHas('client', fn ($q) => $q->where('user_id', $user->id));
            } else {
                // Employee can only see complaints assigned to them
                $query->assignedTo($user->id);
            }
        }

        $query->when($request->filled('status'), fn ($q) => $q->status($request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->priority($request->input('priority')))
            ->when($request->filled('category'), fn ($q) => $q->category($request->input('category')))
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->when($user->isAdmin() && $request->filled('assigned_to'), fn ($q) => $q->assignedTo($request->input('assigned_to')));

        $complaints = $query->latest()->paginate($request->input('per_page', 15));

        $statusLog = $request->input('status', 'all');
        $priorityLog = $request->input('priority', 'all');

        AuditLogService::logView(
            $user,
            'Complaint',
            'List view',
            "Viewed complaints list with filters: status={$statusLog}, priority={$priorityLog}"
        );

        return response()->json([
            'success' => true,
            'data' => $complaints,
        ]);
    }
    /**
     * Store a newly created complaint.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:255',
            'priority' => 'enum:low,medium,high,urgent|default:medium',
        ]);

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only create complaints for themselves
                $client = Client::find($validated['client_id']);
                if ($client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only create complaints for yourself',
                    ], 403);
                }
            }
        }

        DB::beginTransaction();
        try {
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;

            $complaint = Complaint::create($validated);

            DB::commit();

            // Log complaint creation
            AuditLogService::logCreate(
                $user,
                'Complaint',
                $complaint->id,
                "Complaint created: {$validated['title']} (Priority: {$validated['priority']})",
                $complaint->toArray()
            );

            return response()->json([
                'success' => true,
                'message' => 'Complaint created successfully',
                'data' => $complaint->load(['client', 'assignedUser', 'creator']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log failure
            AuditLogService::logFailure(
                $user,
                'Complaint',
                'Create complaint',
                'Failed to create complaint: ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to create complaint',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified complaint.
     */
    public function show(Complaint $complaint): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only see their own complaints
                if ($complaint->client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            } else {
                // Employee can only see complaints assigned to them
                if ($complaint->assigned_to !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            }
        }

        $complaint->load(['client', 'assignedUser', 'creator']);

        // Log view action
        AuditLogService::logView(
            $user,
            'Complaint',
            $complaint->id,
            "Viewed complaint details: {$complaint->title}"
        );

        return response()->json([
            'success' => true,
            'data' => $complaint,
        ]);
    }

    /**
     * Update the specified complaint.
     */
    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $user = Auth::user();

        // Check permissions - only admins can update complaints
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'category' => 'nullable|string|max:255',
            'priority' => 'sometimes|enum:low,medium,high,urgent',
        ]);

        $oldValues = $complaint->toArray();
        $validated['updated_by'] = $user->id;

        $complaint->update($validated);

        // Log update action
        AuditLogService::logUpdate(
            $user,
            'Complaint',
            $complaint->id,
            "Complaint updated: {$complaint->title}",
            $oldValues,
            $complaint->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Complaint updated successfully',
            'data' => $complaint->load(['client', 'assignedUser', 'creator']),
        ]);
    }

    /**
     * Remove the specified complaint.
     */
    public function destroy(Complaint $complaint): JsonResponse
    {
        $user = Auth::user();

        // Check permissions - only admins can delete complaints
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        try {
            $complaintData = $complaint->toArray();
            $complaint->delete();

            // Log deletion
            AuditLogService::logDelete(
                $user,
                'Complaint',
                $complaint->id,
                "Complaint deleted: {$complaint->title}",
                $complaintData
            );

            return response()->json([
                'success' => true,
                'message' => 'Complaint deleted successfully',
            ]);
        } catch (\Exception $e) {
            // Log failure
            AuditLogService::logFailure(
                $user,
                'Complaint',
                'Delete complaint',
                'Failed to delete complaint: ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete complaint',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign complaint to user.
     */
    public function assign(Request $request, Complaint $complaint): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can assign complaints',
            ], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $oldAssignedTo = $complaint->assigned_to;

        $complaint->update([
            'assigned_to' => $validated['assigned_to'],
            'updated_by' => $user->id,
        ]);

        // Log assignment
        AuditLogService::logUpdate(
            $user,
            'Complaint',
            $complaint->id,
            "Complaint assigned from user ID {$oldAssignedTo} to user ID {$validated['assigned_to']}",
            ['assigned_to' => $oldAssignedTo],
            ['assigned_to' => $validated['assigned_to']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Complaint assigned successfully',
            'data' => $complaint->load(['client', 'assignedUser']),
        ]);
    }

    /**
     * Update complaint status.
     */
    public function updateStatus(Request $request, Complaint $complaint): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'status' => 'required|enum:pending,in_progress,resolved,closed',
            'resolution' => 'nullable|string',
        ]);

        // Check permissions
        if (!$user->isAdmin() && !$user->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $oldStatus = $complaint->status;
        $validated['updated_by'] = $user->id;

        if ($validated['status'] === Complaint::STATUS_RESOLVED) {
            $complaint->markAsResolved($validated['resolution'] ?? null);
        } elseif ($validated['status'] === Complaint::STATUS_CLOSED) {
            $complaint->markAsClosed();
        } else {
            $complaint->update($validated);
        }

        // Log status update
        AuditLogService::logUpdate(
            $user,
            'Complaint',
            $complaint->id,
            "Complaint status changed from {$oldStatus} to {$validated['status']}",
            ['status' => $oldStatus],
            ['status' => $validated['status']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Complaint status updated successfully',
            'data' => $complaint->load(['client', 'assignedUser']),
        ])
    }

    /**
     * Add client feedback to complaint.
     */
    public function addFeedback(Request $request, Complaint $complaint): JsonResponse
    {
        $user = Auth::user();

        // Check permissions - only the client who owns the complaint can add feedback
        if (!$user->isClient() || $complaint->client->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        // Can only add feedback to resolved complaints
        if (!$complaint->isResolved()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only add feedback to resolved complaints',
            ], 400);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string',
        ]);

        $complaint->addFeedback($validated['rating'], $validated['comments']);

        // Log feedback
        AuditLogService::logCreate(
            $user,
            'Complaint Feedback',
            $complaint->id,
            "Feedback added for complaint {$complaint->id}: Rating {$validated['rating']}",
            ['rating' => $validated['rating'], 'comments' => $validated['comments']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Feedback added successfully',
            'data' => $complaint,
        ]);
    }

    /**
     * Get complaint statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $query = Complaint::query();

        // Filter based on user role
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                $query->whereHas('client', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } else {
                $query->assignedTo($user->id);
            }
        }

        $stats = [
            'total' => $query->count(),
            'pending' => $query->status(Complaint::STATUS_PENDING)->count(),
            'in_progress' => $query->status(Complaint::STATUS_IN_PROGRESS)->count(),
            'resolved' => $query->status(Complaint::STATUS_RESOLVED)->count(),
            'closed' => $query->status(Complaint::STATUS_CLOSED)->count(),
        ];

        // Add feedback stats for admin
        if ($user->isAdmin()) {
            $stats['with_feedback'] = $query->whereNotNull('feedback_rating')->count();
            $stats['avg_rating'] = $query->whereNotNull('feedback_rating')->avg('feedback_rating') ?? 0;
        }

        // Log statistics view
        AuditLogService::logView(
            $user,
            'Complaint Statistics',
            'Dashboard',
            "Viewed complaint statistics for {$user->role}"
        );

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
