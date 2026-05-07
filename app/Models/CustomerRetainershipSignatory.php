<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRetainershipSignatory extends Model
{
    protected $table = 'customer_retainership_signatories';

    protected $fillable = [
        'form_id',
        'employee_id',
        'role',
        'status',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    // Relationships
    public function form()
    {
        return $this->belongsTo(CustomerRetainershipForm::class, 'form_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
