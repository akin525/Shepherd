<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalTracking extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'goal_trackings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'goal_type_id',
        'subject',
        'description',
        'start_date',
        'end_date',
        'target_value',
        'current_value',
        'unit',
        'priority',
        'status',
        'progress_percentage',
        'notes',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'progress_percentage' => 'decimal:2',
        ];
    }

    /**
     * Get the employee that owns the goal.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the goal type that owns the goal.
     */
    public function goalType(): BelongsTo
    {
        return $this->belongsTo(GoalType::class, 'goal_type_id');
    }

    /**
     * Get the user who created the goal.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include completed goals.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    /**
     * Scope a query to only include in-progress goals.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'In Progress');
    }

    /**
     * Scope a query to only include overdue goals.
     */
    public function scopeOverdue($query)
    {
        return $query->where('end_date', '<', now())
                    ->where('status', '!=', 'Completed');
    }

    /**
     * Get status display name with color
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            'Not Started' => ['text' => 'Not Started', 'color' => '#6C757D'],
            'In Progress' => ['text' => 'In Progress', 'color' => '#007BFF'],
            'Completed' => ['text' => 'Completed', 'color' => '#28A745'],
            'On Hold' => ['text' => 'On Hold', 'color' => '#FFA500'],
            'Cancelled' => ['text' => 'Cancelled', 'color' => '#DC3545'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Get priority display name with color
     */
    public function getPriorityDisplayAttribute(): array
    {
        return match($this->priority) {
            'Low' => ['text' => 'Low', 'color' => '#28A745'],
            'Medium' => ['text' => 'Medium', 'color' => '#FFA500'],
            'High' => ['text' => 'High', 'color' => '#DC3545'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Check if goal is overdue
     */
    public function isOverdue(): bool
    {
        return $this->end_date->isPast() && $this->status !== 'Completed';
    }

    /**
     * Check if goal is due soon (within 7 days)
     */
    public function isDueSoon(): bool
    {
        return $this->end_date->diffInDays(now()) <= 7 && 
               $this->end_date->greaterThanOrEqualTo(now()) && 
               $this->status !== 'Completed';
    }

    /**
     * Get days remaining until deadline
     */
    public function getDaysRemainingAttribute(): int
    {
        if ($this->status === 'Completed') {
            return 0;
        }

        return max(0, now()->diffInDays($this->end_date, false));
    }
}