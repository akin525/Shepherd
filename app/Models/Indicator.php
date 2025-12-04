<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'indicators';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'performance_type_id',
        'created_by',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the performance type that owns the indicator.
     */
    public function performanceType(): BelongsTo
    {
        return $this->belongsTo(PerformanceType::class, 'performance_type_id');
    }

    /**
     * Get the user who created the indicator.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the appraisals for this indicator.
     */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'indicator_id');
    }

    /**
     * Scope a query to only include active indicators.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}