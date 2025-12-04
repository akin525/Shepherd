<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'asset_id',
        'category_id',
        'serial_number',
        'manufacturer',
        'model',
        'purchase_date',
        'purchase_cost',
        'warranty_expiry',
        'employee_id',
        'status',
        'description',
        'notes',
        'attachment',
        'assigned_date',
        'expected_return_date',
        'return_date',
        'return_condition',
        'return_notes',
        'damage_description',
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
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'assigned_date' => 'date',
            'expected_return_date' => 'date',
            'return_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    /**
     * Get the employee that the asset is assigned to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the category that the asset belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the user who created the asset.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include available assets.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'Available');
    }

    /**
     * Scope a query to only include assigned assets.
     */
    public function scopeAssigned($query)
    {
        return $query->where('status', 'Assigned');
    }

    /**
     * Scope a query to only include assets under maintenance.
     */
    public function scopeMaintenance($query)
    {
        return $query->where('status', 'Maintenance');
    }

    /**
     * Scope a query to only include retired assets.
     */
    public function scopeRetired($query)
    {
        return $query->where('status', 'Retired');
    }

    /**
     * Get status display name with color
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            'Available' => ['text' => 'Available', 'color' => '#28A745'],
            'Assigned' => ['text' => 'Assigned', 'color' => '#007BFF'],
            'Maintenance' => ['text' => 'Under Maintenance', 'color' => '#FFA500'],
            'Retired' => ['text' => 'Retired', 'color' => '#6C757D'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Get return condition display name with color
     */
    public function getReturnConditionDisplayAttribute(): array
    {
        return match($this->return_condition) {
            'Excellent' => ['text' => 'Excellent', 'color' => '#28A745'],
            'Good' => ['text' => 'Good', 'color' => '#007BFF'],
            'Fair' => ['text' => 'Fair', 'color' => '#FFA500'],
            'Poor' => ['text' => 'Poor', 'color' => '#DC3545'],
            'Damaged' => ['text' => 'Damaged', 'color' => '#DC3545'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Check if asset is currently assigned
     */
    public function isAssigned(): bool
    {
        return $this->status === 'Assigned' && !is_null($this->employee_id);
    }

    /**
     * Check if asset warranty is expiring soon (within 30 days)
     */
    public function isWarrantyExpiringSoon(): bool
    {
        return $this->warranty_expiry && 
               $this->warranty_expiry->greaterThanOrEqualTo(now()) && 
               $this->warranty_expiry->diffInDays(now()) <= 30;
    }

    /**
     * Check if asset warranty has expired
     */
    public function isWarrantyExpired(): bool
    {
        return $this->warranty_expiry && $this->warranty_expiry->isPast();
    }

    /**
     * Check if asset return is overdue
     */
    public function isReturnOverdue(): bool
    {
        return $this->isAssigned() && 
               $this->expected_return_date && 
               $this->expected_return_date->isPast();
    }

    /**
     * Get days remaining until expected return
     */
    public function getDaysUntilReturnAttribute(): int
    {
        if (!$this->isAssigned() || !$this->expected_return_date) {
            return 0;
        }

        return max(0, now()->diffInDays($this->expected_return_date, false));
    }

    /**
     * Get days remaining until warranty expiry
     */
    public function getDaysUntilWarrantyExpiryAttribute(): int
    {
        if (!$this->warranty_expiry || $this->warranty_expiry->isPast()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->warranty_expiry, false));
    }
}