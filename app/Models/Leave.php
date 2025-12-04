<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leaves';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'applied_on',
        'start_date',
        'end_date',
        'total_leave_days',
        'leave_reason',
        'remark',
        'status',
        'created_by',
        'attachment',
        'approved_by',
        'approval_date',
        'approval_note',
        'rejection_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_on' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'approval_date' => 'datetime',
            'total_leave_days' => 'integer',
        ];
    }

    /**
     * Get the employee that owns the leave.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the leave type that owns the leave.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * Get the user who approved/rejected the leave.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the creator of the leave record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include pending leaves.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    /**
     * Scope a query to only include approved leaves.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    /**
     * Scope a query to only include rejected leaves.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'Reject');
    }

    /**
     * Get status display name with color
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            'Pending' => ['text' => 'Pending', 'color' => '#FFA500'],
            'Approved' => ['text' => 'Approved', 'color' => '#28A745'],
            'Reject' => ['text' => 'Rejected', 'color' => '#DC3545'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Get leave duration display
     */
    public function getDurationDisplayAttribute(): string
    {
        if ($this->total_leave_days == 1) {
            return '1 day';
        }
        
        return $this->total_leave_days . ' days';
    }

    /**
     * Check if leave is currently active
     */
    public function isCurrentlyActive(): bool
    {
        $today = now();
        return $this->status === 'Approved' && 
               $today->between($this->start_date, $this->end_date);
    }

    /**
     * Check if leave is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->status === 'Approved' && 
               now()->lt($this->start_date);
    }

    /**
     * Check if leave has ended
     */
    public function isEnded(): bool
    {
        return $this->status === 'Approved' && 
               now()->gt($this->end_date);
    }
}