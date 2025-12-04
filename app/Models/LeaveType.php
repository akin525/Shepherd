<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leave_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'days',
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
            'days' => 'integer',
        ];
    }

    /**
     * Get the leaves for this leave type.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'leave_type_id');
    }

    /**
     * Scope a query to only include active leave types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}