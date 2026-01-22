<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Trainers extends Model
{

    use HasFactory, Notifiable;

    protected $guarded=[];
    protected $table = "trainers";

    public function training()
    {
        return $this->belongsTo(Trainers::class, 'trainers_id');
    }

    //training_types
//    public function trainingType()
//    {
//        return $this->belongsTo(TrainingType::class, 'trainers_id');
//    }
}
