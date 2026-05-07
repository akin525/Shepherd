<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRetainershipEquipment extends Model
{
    protected $table = 'customer_retainership_equipments';

    protected $fillable = [
        'form_id', 'device', 'cost', 'quantity',
        'monthly_service_cost', 'billing_per_month',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'monthly_service_cost' => 'decimal:2',
        'billing_per_month' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'device');
    }
}
