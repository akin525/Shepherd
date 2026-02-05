<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    public function against(): BelongsTo
    {
        return $this->belongsTo(ClientStaff::class, 'complaint_against');
    }
}
