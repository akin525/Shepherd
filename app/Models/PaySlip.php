<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaySlip extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pay_slips';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'payment_date',
        'basic_salary',
        'allowance',
        'deduction',
        'gross_salary',
        'net_salary',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'payslip_type_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'approved_at' => 'datetime',
            'allowance' => 'array',
            'deduction' => 'array',
            'basic_salary' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    /**
     * Get the employee that owns the payslip.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the user who created the payslip.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the payslip.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope a query to only include paid payslips.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'Paid');
    }

    /**
     * Scope a query to only include unpaid payslips.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', 'Unpaid');
    }

    /**
     * Get status display name with color
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            'Paid' => ['text' => 'Paid', 'color' => '#28A745'],
            'Unpaid' => ['text' => 'Unpaid', 'color' => '#FFA500'],
            default => ['text' => 'Unknown', 'color' => '#6C757D'],
        };
    }

    /**
     * Get total allowances amount
     */
    public function getTotalAllowancesAttribute(): float
    {
        if (!$this->allowance || !is_array($this->allowance)) {
            return 0;
        }

        return array_sum($this->allowance);
    }

    /**
     * Get total deductions amount
     */
    public function getTotalDeductionsAttribute(): float
    {
        if (!$this->deduction || !is_array($this->deduction)) {
            return 0;
        }

        return array_sum($this->deduction);
    }
}