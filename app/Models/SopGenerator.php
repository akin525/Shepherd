<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SopGenerator extends Model
{
    use HasFactory;

    protected $fillable = [
        'sop_title',
        'client_name',
        'client_id',
        'location',
        'effective_date',
        'procedure_steps',
        'responsibilities',
        'emergency_instructions',
        'document',
    ];

    // Automatically cast JSON to array
    protected $casts = [
        'effective_date' => 'date',
        'procedure_steps' => 'array',
        'responsibilities' => 'array',
        'emergency_instructions' => 'array',
    ];
}
