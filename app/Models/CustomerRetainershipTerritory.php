<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRetainershipTerritory extends Model
{
    protected $table = 'customer_retainership_teritories';

    protected $fillable = [
        'form_id',
        'region',
        'zone',
        'ops_officer_in_charge',
        'responsible_staff',
        'hod_in_charge',
        'operations_manager',
        'credit_controller_region',
        'business_dev_manager',
    ];

    // RELATIONSHIPS TO EMPLOYEES
    public function opsOfficer()
    {
        return $this->belongsTo(Employee::class, 'ops_officer_in_charge');
    }

    public function responsibleStaff()
    {
        return $this->belongsTo(Employee::class, 'responsible_staff');
    }

    public function hod()
    {
        return $this->belongsTo(Employee::class, 'hod_in_charge');
    }

    public function operationsManager()
    {
        return $this->belongsTo(Employee::class, 'operations_manager');
    }

    public function creditController()
    {
        return $this->belongsTo(Employee::class, 'credit_controller_region');
    }

    public function bdm()
    {
        return $this->belongsTo(Employee::class, 'business_dev_manager');
    }
}
