<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['client', 'assignedUser', 'creator', 'replies']);

        // Filter by client if not admin
        $user = Auth::user();
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only see their own tickets
                $query->whereHas('client', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } else {
                // Employee can only see tickets assigned to them
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
        $tickets = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'enum:low,medium,high,urgent|default:medium',
            'category' => 'nullable|string|max:255',
        ]);

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only create tickets for themselves
                $client = Client::find($validated['client_id']);
                if ($client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only create tickets for yourself',
                    ], 403);
                }
            }
        }

        DB::beginTransaction();
        try {
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;

            $ticket = Ticket::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => $ticket->load(['client', 'assignedUser', 'creator']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only see their own tickets
                if ($ticket->client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            } else {
                // Employee can only see tickets assigned to them
                if ($ticket->assigned_to !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            }
        }

        $ticket->load(['client', 'assignedUser', 'creator', 'replies.user']);

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Update the specified ticket.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin() && $ticket->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $validated = $request->validate([
            'subject' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'priority' => 'sometimes|enum:low,medium,high,urgent',
            'category' => 'nullable|string|max:255',
        ]);

        $validated['updated_by'] = $user->id;

        $ticket->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'data' => $ticket->load(['client', 'assignedUser', 'creator']),
        ]);
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin() && $ticket->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        try {
            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign ticket to user.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can assign tickets',
            ], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully',
            'data' => $ticket->load(['client', 'assignedUser']),
        ]);
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'status' => 'required|enum:open,in_progress,resolved,closed',
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

        if ($validated['status'] === Ticket::STATUS_RESOLVED) {
            $ticket->markAsResolved($validated['resolution'] ?? null);
        } elseif ($validated['status'] === Ticket::STATUS_CLOSED) {
            $ticket->markAsClosed();
        } else {
            $ticket->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully',
            'data' => $ticket->load(['client', 'assignedUser']),
        ]);
    }

    /**
     * Add reply to ticket.
     */
    public function addReply(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        // Check permissions
        if (!$user->isAdmin()) {
            if ($user->isClient()) {
                // Client can only reply to their own tickets
                if ($ticket->client->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            } else {
                // Employee can only reply to tickets assigned to them
                if ($ticket->assigned_to !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                    ], 403);
                }
            }
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $replyData = [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_internal' => $validated['is_internal'] ?? false,
            'created_by' => $user->id,
        ];

        $reply = TicketReply::create($replyData);

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'data' => $reply->load('user'),
        ], 201);
    }

    /**
     * Get ticket statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        
        $query = Ticket::query();
        
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
            'open' => $query->status(Ticket::STATUS_OPEN)->count(),
            'in_progress' => $query->status(Ticket::STATUS_IN_PROGRESS)->count(),
            'resolved' => $query->status(Ticket::STATUS_RESOLVED)->count(),
            'closed' => $query->status(Ticket::STATUS_CLOSED)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}