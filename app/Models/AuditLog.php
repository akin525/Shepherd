<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'action_type',
        'feature',
        'target',
        'description',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope to filter by action type
     */
    public function scopeByActionType($query, $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope to filter by feature
     */
    public function scopeByFeature($query, $feature)
    {
        return $query->where('feature', $feature);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get action type badge color
     */
    public function getActionBadgeColor(): string
    {
        return match($this->action_type) {
            'Created' => 'bg-green-100 text-green-800',
            'Updated' => 'bg-blue-100 text-blue-800',
            'Deleted' => 'bg-red-100 text-red-800',
            'Logged' => 'bg-gray-100 text-gray-800',
            'Viewed' => 'bg-purple-100 text-purple-800',
            'Approved' => 'bg-emerald-100 text-emerald-800',
            'Rejected' => 'bg-rose-100 text-rose-800',
            'Exported' => 'bg-yellow-100 text-yellow-800',
            'Imported' => 'bg-indigo-100 text-indigo-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}