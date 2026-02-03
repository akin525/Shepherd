<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'complaint_from',
        'client_id',
        'complaint_against',
        'title',
        'complaint_date',
        'description',
        'attachment',
        'status',
        'priority',
        'created_by'
    ];


}
