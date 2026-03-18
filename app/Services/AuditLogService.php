<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an action to the audit log
     *
     * @param int|null $userId
     * @param string $userName
     * @param string|null $userEmail
     * @param string $userRole
     * @param string $actionType
     * @param string $feature
     * @param string|null $target
     * @param string $description
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param string $status
     * @return AuditLog
     */
    public static function log(
        ?int $userId,
        string $userName,
        ?string $userEmail,
        string $userRole,
        string $actionType,
        string $feature,
        ?string $target,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = 'success'
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'user_role' => $userRole,
            'action_type' => $actionType,
            'feature' => $feature,
            'target' => $target,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'status' => $status,
        ]);
    }

    /**
     * Log a create action
     */
    public static function logCreate(
        $user,
        string $feature,
        string $target,
        string $description,
        ?array $newValues = null
    ): AuditLog {
        return self::log(
            $user?->id,
            $user?->name ?? 'System',
            $user?->email,
            $user?->getRoleDisplayName() ?? 'System',
            'Created',
            $feature,
            $target,
            $description,
            null,
            $newValues
        );
    }

    /**
     * Log an update action
     */
    public static function logUpdate(
        $user,
        string $feature,
        string $target,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return self::log(
            $user?->id,
            $user?->name ?? 'System',
            $user?->email,
            $user?->getRoleDisplayName() ?? 'System',
            'Updated',
            $feature,
            $target,
            $description,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log a delete action
     */
    public static function logDelete(
        $user,
        string $feature,
        string $target,
        string $description,
        ?array $oldValues = null
    ): AuditLog {
        return self::log(
            $user?->id,
            $user?->name ?? 'System',
            $user?->email,
            $user?->getRoleDisplayName() ?? 'System',
            'Deleted',
            $feature,
            $target,
            $description,
            $oldValues,
            null
        );
    }

    /**
     * Log a view action
     */
    public static function logView(
        $user,
        string $feature,
        string $target,
        string $description
    ): AuditLog {
        return self::log(
            $user?->id,
            $user?->name ?? 'System',
            $user?->email,
            $user?->getRoleDisplayName() ?? 'System',
            'Viewed',
            $feature,
            $target,
            $description
        );
    }

    /**
     * Log a login action
     */
    public static function logLogin(
        $user,
        string $description = 'User logged in'
    ): AuditLog {
        return self::log(
            $user?->id,
            $user?->name ?? 'System',
            $user?->email,
            $user?->getRoleDisplayName() ?? 'System',
            'Logged',
            'Authentication',
            'Login',
            $description
        );
    }

    /**
     * Log a logout action
     */
    public static function logLogout(
        $user,
        string $description = 'User logged out'
    ): AuditLog {
        return self::log(
            $user?->id,
            $user?->name ?? 'System',
            $user?->email,
            $user?->getRoleDisplayName() ?? 'System',
            'Logged',
            'Authentication',
            'Logout',
            $description
        );
    }

    /**
     * Log a failed action
     */
    public static function logFailure(
        $user,
        string $actionType,
        string $feature,
        string $target,
        string $description
    ): AuditLog {
        return self::log(
            $user?->id,
            $user?->name ?? 'System',
            $user?->email,
            $user?->getRoleDisplayName() ?? 'System',
            $actionType,
            $feature,
            $target,
            $description,
            null,
            null,
            'failed'
        );
    }

    /**
     * Get audit logs with filtering
     */
    public static function getAuditLogs(array $filters = [])
    {
        $query = AuditLog::with('user');

        if (isset($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (isset($filters['action_type'])) {
            $query->byActionType($filters['action_type']);
        }

        if (isset($filters['feature'])) {
            $query->byFeature($filters['feature']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->byDateRange($filters['start_date'], $filters['end_date']);
        }

        if (isset($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('user_name', 'like', "%{$searchTerm}%")
                    ->orWhere('feature', 'like', "%{$searchTerm}%")
                    ->orWhere('target', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        return $query->orderBy('created_at', 'desc');
    }
}
