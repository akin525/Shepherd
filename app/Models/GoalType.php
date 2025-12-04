<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoalType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'goal_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
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
     * Get the goals for this type.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(GoalTracking::class, 'goal_type_id');
    }

    /**
     * Scope a query to only include active goal types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}