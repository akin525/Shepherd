<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\Complaint;
use App\Models\ClientDocument;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Get client dashboard statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        // Get client associated with the user
        $client = Client::where('user_id', $user->id)->first();
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found',
            ], 404);
        }

        $stats = [
            'profile' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'status' => $client->status,
                'company' => $client->company,
            ],
            'tickets' => [
                'total' => $client->tickets()->count(),
                'open' => $client->tickets()->open()->count(),
                'in_progress' => $client->tickets()->status('in_progress')->count(),
                'resolved' => $client->tickets()->status('resolved')->count(),
                'closed' => $client->tickets()->status('closed')->count(),
            ],
            'complaints' => [
                'total' => $client->complaints()->count(),
                'pending' => $client->complaints()->status('pending')->count(),
                'in_progress' => $client->complaints()->status('in_progress')->count(),
                'resolved' => $client->complaints()->status('resolved')->count(),
                'closed' => $client->complaints()->status('closed')->count(),
            ],
            'documents' => [
                'total' => $client->documents()->count(),
                'active' => $client->documents()->status('active')->count(),
                'total_size' => $client->documents()->sum('file_size'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get client recent activities.
     */
    public function recentActivities(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $client = Client::where('user_id', $user->id)->first();
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found',
            ], 404);
        }

        $limit = $request->get('limit', 10);
        $activities = [];

        // Recent tickets
        $tickets = $client->tickets()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'type' => 'ticket',
                    'title' => 'Support Ticket',
                    'description' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'date' => $ticket->created_at,
                    'status_color' => $ticket->status_color,
                    'priority_color' => $ticket->priority_color,
                ];
            });

        // Recent complaints
        $complaints = $client->complaints()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($complaint) {
                return [
                    'id' => $complaint->id,
                    'type' => 'complaint',
                    'title' => 'Complaint',
                    'description' => $complaint->title,
                    'status' => $complaint->status,
                    'priority' => $complaint->priority,
                    'date' => $complaint->created_at,
                    'status_color' => $complaint->status_color,
                    'priority_color' => $complaint->priority_color,
                ];
            });

        // Recent documents
        $documents = $client->documents()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,
                    'type' => 'document',
                    'title' => 'Document',
                    'description' => $document->title,
                    'status' => $document->status,
                    'category' => $document->category,
                    'date' => $document->created_at,
                    'file_size' => $document->formatted_file_size,
                    'file_type' => $document->file_type,
                ];
            });

        // Combine and sort
        $activities = $tickets->merge($complaints)
            ->merge($documents)
            ->sortByDesc('date')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get client announcements.
     */
    public function announcements(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $query = Announcement::where('status', 'published')
            ->where(function ($q) {
                $q->where('target_audience', 'all')
                  ->orWhere('target_audience', 'clients');
            })
            ->latest();

        $perPage = $request->get('per_page', 15);
        $announcements = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    /**
     * Get client calendar events.
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $client = Client::where('user_id', $user->id)->first();
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found',
            ], 404);
        }

        $start = $request->get('start');
        $end = $request->get('end');
        $events = [];

        // Ticket due dates
        $tickets = $client->tickets()
            ->open()
            ->when($start, function ($query, $start) {
                $query->whereDate('created_at', '>=', $start);
            })
            ->when($end, function ($query, $end) {
                $query->whereDate('created_at', '<=', $end);
            })
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'title' => 'Ticket: ' . $ticket->subject,
                    'start' => $ticket->created_at->format('Y-m-d'),
                    'type' => 'ticket',
                    'priority' => $ticket->priority,
                    'color' => $ticket->priority_color,
                ];
            });

        // Complaint events
        $complaints = $client->complaints()
            ->open()
            ->when($start, function ($query, $start) {
                $query->whereDate('created_at', '>=', $start);
            })
            ->when($end, function ($query, $end) {
                $query->whereDate('created_at', '<=', $end);
            })
            ->get()
            ->map(function ($complaint) {
                return [
                    'id' => $complaint->id,
                    'title' => 'Complaint: ' . $complaint->title,
                    'start' => $complaint->created_at->format('Y-m-d'),
                    'type' => 'complaint',
                    'priority' => $complaint->priority,
                    'color' => $complaint->priority_color,
                ];
            });

        // Merge events
        $events = $tickets->merge($complaints)->values();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get client notifications.
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $client = Client::where('user_id', $user->id)->first();
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found',
            ], 404);
        }

        $limit = $request->get('limit', 20);
        $notifications = [];

        // New ticket responses
        $ticketReplies = $client->tickets()
            ->with('replies.user')
            ->whereHas('replies', function ($query) use ($user) {
                $query->where('user_id', '!=', $user->id)
                      ->where('created_at', '>', now()->subDays(7));
            })
            ->get()
            ->flatMap(function ($ticket) {
                return $ticket->replies->map(function ($reply) use ($ticket) {
                    return [
                        'id' => $reply->id,
                        'type' => 'ticket_reply',
                        'title' => 'New reply to your ticket',
                        'message' => $reply->user->name . ' replied to: ' . $ticket->subject,
                        'date' => $reply->created_at,
                        'read' => false,
                        'priority' => 'info',
                    ];
                });
            });

        // Complaint updates
        $complaintUpdates = $client->complaints()
            ->where('updated_at', '>', now()->subDays(7))
            ->where('updated_at', '!=', 'created_at')
            ->get()
            ->map(function ($complaint) {
                return [
                    'id' => $complaint->id,
                    'type' => 'complaint_update',
                    'title' => 'Complaint status updated',
                    'message' => 'Your complaint "' . $complaint->title . '" is now ' . $complaint->status,
                    'date' => $complaint->updated_at,
                    'read' => false,
                    'priority' => $complaint->status === 'resolved' ? 'success' : 'info',
                ];
            });

        // Merge notifications
        $notifications = $ticketReplies->merge($complaintUpdates)
            ->sortByDesc('date')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Get quick actions for client dashboard.
     */
    public function quickActions(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $actions = [
            [
                'title' => 'Create Ticket',
                'description' => 'Submit a new support ticket',
                'icon' => 'ticket',
                'route' => '/tickets/create',
                'color' => 'primary',
            ],
            [
                'title' => 'File Complaint',
                'description' => 'Submit a new complaint',
                'icon' => 'complaint',
                'route' => '/complaints/create',
                'color' => 'warning',
            ],
            [
                'title' => 'Upload Document',
                'description' => 'Upload a new document',
                'icon' => 'document',
                'route' => '/documents/create',
                'color' => 'info',
            ],
            [
                'title' => 'View Profile',
                'description' => 'Update your profile information',
                'icon' => 'profile',
                'route' => '/profile',
                'color' => 'secondary',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }
}