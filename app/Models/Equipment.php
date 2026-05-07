<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasFactory;
    protected $table = 'equipments';
    protected $fillable = [
        'name',
        'type',
        'size',
        'quantity',
        'created_by',
    ];

    // App\Models\Equipment.php
    public function equipments_movements()
    {
        return $this->hasMany(EquipmentMovement::class, 'equipment_id', 'id');
    }
}
