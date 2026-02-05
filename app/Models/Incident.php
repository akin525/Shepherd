<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id', 'title', 'location', 'incident_date',
        'incident_time', 'reported_by', 'guard_id', 'description',
        'action_taken', 'evidence_photos', 'status'
    ];

    protected $casts = [
        'incident_date' => 'date',
        'evidence_photos' => 'array',
    ];
}
