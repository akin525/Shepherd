<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class EscalationType extends Model
{

    use HasFactory, Notifiable;

    protected $guarded=[];
    protected $table = "escalation_types";



}
