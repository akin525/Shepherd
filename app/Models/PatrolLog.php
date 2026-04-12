<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatrolLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'guard_name', 'location', 'patrol_area', 'patrol_date',
        'patrol_time', 'observation', 'incident_found',
        'incident_description', 'evidence_files', 'status', 'meta'
    ];

    protected $casts = [
        'patrol_date' => 'date',
        'incident_found' => 'boolean',
        'evidence_files' => 'array',
    ];
}
