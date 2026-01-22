<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Award extends Model
{

    use HasFactory, Notifiable;

    protected $guarded=[];
    protected $table = "awards";

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

}
