<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRetainershipContact extends Model
{
    protected $fillable = ['form_id', 'role', 'name', 'email', 'phone'];

    public function form()
    {
        return $this->belongsTo(CustomerRetainershipForm::class, 'form_id');
    }
}
