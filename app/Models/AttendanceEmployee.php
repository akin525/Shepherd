<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEmployee extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendance_employees';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'date',
        'status',
        'clock_in',
        'clock_out',
        'late',
        'early_leaving',
        'overtime',
        'total_rest',
        'total_work',
        'clock_in_ip',
        'clock_out_ip',
        'clock_in_location',
        'clock_out_location',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_latitude',
        'clock_out_longitude',
        'notes',
        'created_by',
        'half_day',
        'adjusted_by',
        'adjusted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime:H:i:s',
            'clock_out' => 'datetime:H:i:s',
            'adjusted_at' => 'datetime',
        ];
    }

    /**
     * Get the employee that owns the attendance.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the user who created the attendance record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who adjusted the attendance record.
     */
    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /**
     * Scope a query to only include present attendances.
     */
    public function scopePresent($query)
    {
        return $query->where('status', 'Present');
    }

    /**
     * Scope a query to only include absent attendances.
     */
    public function scopeAbsent($query)
    {
        return $query->where('status', 'Absent');
    }

    /**
     * Scope a query to only include late attendances.
     */
    public function scopeLate($query)
    {
        return $query->where('late', 'Yes');
    }

    /**
     * Scope a query to only include early leaving attendances.
     */
    public function scopeEarlyLeaving($query)
    {
        return $query->where('early_leaving', 'Yes');
    }

    /**
     * Scope a query to only include half day attendances.
     */
    public function scopeHalfDay($query)
    {
        return $query->where('half_day', 'Yes');
    }

    /**
     * Get status display name with color
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            'Present' => ['text' => 'Present', 'color' => '#28A745'],
            'Absent' => ['text' => 'Absent', 'color' => '#DC3545'],
            'Leave' => ['text' => 'On Leave', 'color' => '#FFA500'],
            'Holiday' => ['text' => 'Holiday', 'color' => '#007BFF'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Check if employee is checked in
     */
    public function isCheckedIn(): bool
    {
        return !is_null($this->clock_in);
    }

    /**
     * Check if employee is checked out
     */
    public function isCheckedOut(): bool
    {
        return !is_null($this->clock_out);
    }

    /**
     * Check if attendance is complete (both check-in and check-out)
     */
    public function isComplete(): bool
    {
        return $this->isCheckedIn() && $this->isCheckedOut();
    }

    /**
     * Check if employee is currently checked in (not checked out yet)
     */
    public function isCurrentlyCheckedIn(): bool
    {
        return $this->isCheckedIn() && !$this->isCheckedOut();
    }

    /**
     * Get duration in hours
     */
    public function getDurationInHours(): float
    {
        if (!$this->isComplete()) {
            return 0;
        }

        $clockIn = \Carbon\Carbon::parse($this->clock_in);
        $clockOut = \Carbon\Carbon::parse($this->clock_out);
        
        return $clockIn->diffInMinutes($clockOut) / 60;
    }

    /**
     * Get duration display
     */
    public function getDurationDisplayAttribute(): string
    {
        if (!$this->total_work) {
            return 'Not completed';
        }
        
        return $this->total_work;
    }

    /**
     * Check if attendance was adjusted
     */
    public function isAdjusted(): bool
    {
        return !is_null($this->adjusted_by);
    }
}