<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRetainershipForm extends Model
{
    protected $table = 'customer_retainership_forms';

    protected $fillable = [
        'client_id', 'code', 'issue_date', 'revision_date', 'new_activation',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'revision_date' => 'date',
        'new_activation' => 'boolean',
    ];

    // RELATIONSHIPS
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function contacts()
    {
        return $this->hasMany(CustomerRetainershipContact::class, 'form_id');
    }

    public function territories()
    {
        return $this->hasOne(CustomerRetainershipTerritory::class, 'form_id');
    }

    public function services()
    {
        return $this->hasMany(CustomerRetainershipService::class, 'form_id');
    }

    public function equipments()
    {
        return $this->hasMany(CustomerRetainershipEquipment::class, 'form_id');
    }

    public function signatories()
    {
        return $this->hasMany(CustomerRetainershipSignatory::class, 'form_id');
    }

    public function hasSignatory($employeeId)
    {
        return $this->signatories->contains('employee_id', $employeeId);
    }
}
