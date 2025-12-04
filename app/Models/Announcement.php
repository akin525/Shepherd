<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'announcements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'category',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'attachment',
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
        ];
    }

    /**
     * Get the user who created the announcement.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the departments for the announcement.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'announcement_departments');
    }

    /**
     * Get the designations for the announcement.
     */
    public function designations(): BelongsToMany
    {
        return $this->belongsToMany(Designation::class, 'announcement_designations');
    }

    /**
     * Get the employees for the announcement.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'announcement_employees')
                    ->withPivot('read_at')
                    ->withTimestamps();
    }

    /**
     * Scope a query to only include active announcements.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope a query to get current announcements.
     */
    public function scopeCurrent($query)
    {
        return $query->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                    });
    }

    /**
     * Check if announcement is currently active
     */
    public function isCurrentlyActive(): bool
    {
        return $this->status === 'Active' && 
               $this->start_date->lessThanOrEqualTo(now()) && 
               (!$this->end_date || $this->end_date->greaterThanOrEqualTo(now()));
    }

    /**
     * Get status display name with color
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            'Active' => ['text' => 'Active', 'color' => '#28A745'],
            'Inactive' => ['text' => 'Inactive', 'color' => '#6C757D'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }
}