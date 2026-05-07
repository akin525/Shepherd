<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRetainershipService extends Model
{
    protected $table = 'customer_retainership_services';

    protected $fillable = [
        'form_id',
        'service_id', // Changed from 'grade' to 'service_id' for better indexing
        'grade',      // We still store the name as a snapshot
        'shift_pattern',
        'guard_monthly_net',
        'quantity',
        'gross_billing_per_guard',
        'billing_per_month',
    ];

    protected $casts = [
        'guard_monthly_net' => 'decimal:2',
        'gross_billing_per_guard' => 'decimal:2',
        'billing_per_month' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relationship to the master services table
    public function serviceMaster()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function form()
    {
        return $this->belongsTo(CustomerRetainershipForm::class, 'form_id');
    }
}
