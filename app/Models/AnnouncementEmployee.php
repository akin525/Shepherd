<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementEmployee extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'announcement_employees';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'announcement_id',
        'employee_id',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the announcement that owns the pivot.
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id');
    }

    /**
     * Get the employee that owns the pivot.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Check if announcement is read
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}