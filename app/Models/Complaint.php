<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'assigned_to',
        'created_by',
        'updated_by',
        'resolved_at',
        'resolution',
        'feedback_rating',
        'feedback_comments',
    ];



    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Get the client associated with the complaint.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created the complaint.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the complaint.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user assigned to the complaint.
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to filter by assigned user.
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope a query to search complaints by title or description.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to get open complaints.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'danger',
            self::STATUS_IN_PROGRESS => 'warning',
            self::STATUS_RESOLVED => 'success',
            self::STATUS_CLOSED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get the priority color for UI display.
     */
    public function getPriorityColorAttribute()
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'danger',
            self::PRIORITY_HIGH => 'warning',
            self::PRIORITY_MEDIUM => 'info',
            self::PRIORITY_LOW => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Mark the complaint as resolved.
     */
    public function markAsResolved($resolution = null)
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolved_at = now();
        if ($resolution) {
            $this->resolution = $resolution;
        }
        $this->save();
    }

    /**
     * Mark the complaint as closed.
     */
    public function markAsClosed()
    {
        $this->status = self::STATUS_CLOSED;
        $this->save();
    }

    /**
     * Add client feedback.
     */
    public function addFeedback($rating, $comments = null)
    {
        $this->feedback_rating = $rating;
        $this->feedback_comments = $comments;
        $this->save();
    }

    /**
     * Check if the complaint is resolved.
     */
    public function isResolved()
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Check if the complaint is closed.
     */
    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Check if the complaint has feedback.
     */
    public function hasFeedback()
    {
        return $this->feedback_rating !== null;
    }

    /**
     * Get the feedback rating stars (1-5).
     */
    public function getFeedbackStarsAttribute()
    {
        return str_repeat('★', $this->feedback_rating) . str_repeat('☆', 5 - $this->feedback_rating);
    }

    /**
     * Get the resolution time in days.
     */
    public function getResolutionTimeAttribute()
    {
        if (!$this->resolved_at) {
            return null;
        }

        return $this->created_at->diffInDays($this->resolved_at);
    }
}
