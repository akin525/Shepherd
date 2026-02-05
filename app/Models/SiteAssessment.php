<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAssessment extends Model
{
    use HasFactory;

    protected $table = 'site_assessments';

    protected $fillable = [
        'request_id',
        'client_id',
        'site_name',
        'site_address',
        'location',
        'facility_type',
        'assessment_date',
        'assessment_time',
        'assessed_by',
        'guard_strength',
        'cadre_type',
        'armed_police_required',
        'shift_pattern',
        'security_risks',
        'general_observations',
        'status',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'assessment_time' => 'datetime',
        'armed_police_required' => 'boolean',
        'security_risks' => 'array',
        'general_observations' => 'array',
    ];


    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
