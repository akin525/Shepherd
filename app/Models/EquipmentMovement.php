<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentMovement extends Model
{
    use HasFactory;

    public function equipment()
    {
        return $this->hasOne(Equipment::class, 'id', 'equipment_id');
    }

    public function employee()
    {
        return $this->hasOne(Property::class, 'id', 'employee_id');
    }
}
