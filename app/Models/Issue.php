<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'recipient_id',
        'title',
        'description',
        'category',
        'status',
    ];

    /**
     * Relationship: Who reported this issue?
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Who is this issue assigned to?
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
