<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class ClientStaff extends Model
{

    use HasFactory, Notifiable;

    protected $guarded=[];
    protected $table = "client_staffs";



}
