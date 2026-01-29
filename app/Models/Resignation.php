<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resignation extends Model
{
    use HasFactory;

    protected $table = 'resignations';

    protected $fillable = [
        'employee_id',
        'notice_date',
        'resignation_date',
        'description',
        'created_by',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'resignation_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
