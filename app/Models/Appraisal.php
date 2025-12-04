<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appraisal extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'appraisals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'appraisal_type_id',
        'indicator_id',
        'rating',
        'remark',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the employee that owns the appraisal.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the appraisal type that owns the appraisal.
     */
    public function appraisalType(): BelongsTo
    {
        return $this->belongsTo(PerformanceType::class, 'appraisal_type_id');
    }

    /**
     * Get the indicator that owns the appraisal.
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    /**
     * Get the user who created the appraisal.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the appraisal.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope a query to only include completed appraisals.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    /**
     * Scope a query to only include pending appraisals.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    /**
     * Get status display name with color
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            'Pending' => ['text' => 'Pending', 'color' => '#FFA500'],
            'Completed' => ['text' => 'Completed', 'color' => '#28A745'],
            'Rejected' => ['text' => 'Rejected', 'color' => '#DC3545'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Get rating display with color
     */
    public function getRatingDisplayAttribute(): array
    {
        $rating = (float) $this->rating;
        
        if ($rating >= 4.5) {
            return ['text' => 'Excellent', 'color' => '#28A745', 'stars' => 5];
        } elseif ($rating >= 3.5) {
            return ['text' => 'Good', 'color' => '#007BFF', 'stars' => 4];
        } elseif ($rating >= 2.5) {
            return ['text' => 'Average', 'color' => '#FFA500', 'stars' => 3];
        } elseif ($rating >= 1.5) {
            return ['text' => 'Below Average', 'color' => '#FF6B6B', 'stars' => 2];
        } else {
            return ['text' => 'Poor', 'color' => '#DC3545', 'stars' => 1];
        }
    }
}