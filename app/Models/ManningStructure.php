<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManningStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_name',
        'location',
        'start_date',
        'total_guards',
        'shift_setup',
    ];

    // Automatically cast JSON to array
    protected $casts = [
        'start_date' => 'date',
        'shift_setup' => 'array',
    ];
}
