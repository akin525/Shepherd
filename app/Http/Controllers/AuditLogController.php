<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * Get all audit logs with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'user_id' => 'sometimes|integer|exists:users,id',
            'action_type' => 'sometimes|string',
            'feature' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'search' => 'sometimes|string|max:255',
        ]);

        $perPage = $request->input('per_page', 50);
        $filters = $request->only(['user_id', 'action_type', 'feature', 'start_date', 'end_date', 'search']);

        $auditLogs = AuditLogService::getAuditLogs($filters)
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $auditLogs->items(),
            'pagination' => [
                'total' => $auditLogs->total(),
                'per_page' => $auditLogs->perPage(),
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'from' => $auditLogs->firstItem(),
                'to' => $auditLogs->lastItem(),
            ],
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'user_id']);

        $query = AuditLogService::getAuditLogs($filters);

        $stats = [
            'total_logs' => $query->count(),
            'by_action_type' => (clone $query)->selectRaw('action_type, COUNT(*) as count')
                ->groupBy('action_type')
                ->pluck('count', 'action_type')
                ->toArray(),
            'by_feature' => (clone $query)->selectRaw('feature, COUNT(*) as count')
                ->groupBy('feature')
                ->pluck('count', 'feature')
                ->toArray(),
            'recent_activity' => $query->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get a specific audit log
     */
    public function show(int $id): JsonResponse
    {
        $auditLog = AuditLogService::getAuditLogs()
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $auditLog,
        ]);
    }

    /**
     * Export audit logs
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'format' => 'sometimes|string|in:csv,json',
        ]);

        $filters = $request->only(['start_date', 'end_date', 'user_id', 'action_type', 'feature']);
        $format = $request->input('format', 'json');

        $auditLogs = AuditLogService::getAuditLogs($filters)->get();

        $data = $auditLogs->map(function ($log) {
            return [
                'Date & Time' => $log->created_at->format('d M Y, h:i A'),
                'User' => $log->user_name,
                'User Email' => $log->user_email,
                'User Role' => $log->user_role,
                'Action Type' => $log->action_type,
                'Feature' => $log->feature,
                'Target' => $log->target,
                'Description' => $log->description,
                'IP Address' => $log->ip_address,
                'Status' => $log->status,
            ];
        });

        if ($format === 'csv') {
            // CSV export logic would go here
            return response()->json([
                'success' => true,
                'message' => 'CSV export feature - implement CSV generation',
                'data' => $data,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
