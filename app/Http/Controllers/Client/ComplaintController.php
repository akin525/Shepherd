<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    /**
     * Display a listing of complaints.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with(['client', 'assignedUser', 'creator']);

        // Filter by client if not admin
        $user = Auth::user();
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only see their own complaints
                $query->whereHas('client', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } else {
                // Employee can only see complaints assigned to them
                $query->assignedTo($user->id);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->status($request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->priority($request->priority);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->category($request->category);
        }

        // Filter by assigned user (admin only)
        if ($request->has('assigned_to') && $user->isAdmin()) {
            $query->assignedTo($request->assigned_to);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $perPage = $request->get('per_page', 15);
        $complaints = $query->latest()->paginate($perPage);

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

            return response()->json([
                'success' => true,
                'message' => 'Complaint created successfully',
                'data' => $complaint->load(['client', 'assignedUser', 'creator']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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

        $validated['updated_by'] = $user->id;

        $complaint->update($validated);

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
            $complaint->delete();

            return response()->json([
                'success' => true,
                'message' => 'Complaint deleted successfully',
            ]);
        } catch (\Exception $e) {
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

        $complaint->update([
            'assigned_to' => $validated['assigned_to'],
            'updated_by' => $user->id,
        ]);

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

        $validated['updated_by'] = $user->id;

        if ($validated['status'] === Complaint::STATUS_RESOLVED) {
            $complaint->markAsResolved($validated['resolution'] ?? null);
        } elseif ($validated['status'] === Complaint::STATUS_CLOSED) {
            $complaint->markAsClosed();
        } else {
            $complaint->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Complaint status updated successfully',
            'data' => $complaint->load(['client', 'assignedUser']),
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}