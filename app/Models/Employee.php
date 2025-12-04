<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'employees';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'password',
        'employee_id',
        'phone',
        'company_id',
        'branch_id',
        'department_id',
        'designation_id',
        'gender',
        'dob',
        'age',
        'date_of_joining',
        'date_of_leaving',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'marital_status',
        'emergency_contact',
        'emergency_contact_name',
        'emergency_contact_relation',
        'bank_account_name',
        'bank_account_number',
        'bank_name',
        'bank_address',
        'bank_identifier_code',
        'social_security_number',
        'status',
        'is_active',
        'avatar',
        'salary',
        'salary_type',
        'salary_type_id',
        'created_by',
        'document_collection_require',
        'id_expiry_notification',
        'document_expiry_notification',
        'probation_period',
        'notice_period',
        'contract_type_id',
        'contract_start_date',
        'contract_end_date',
        'space_allocation',
        'is_driver',
        'is_security_guard',
        'vehicle_number',
        'license_number',
        'license_expiry_date',
        'training_completed',
        'shift_schedule',
        'duty_hours',
        'break_hours',
        'overtime_eligible',
        'leave_balance',
        'sick_leave_balance',
        'casual_leave_balance',
        'maternity_leave_balance',
        'paternity_leave_balance',
        'performance_rating',
        'last appraisal_date',
        'next appraisal_date',
        'skills',
        'qualifications',
        'experience_years',
        'previous_employer',
        'previous_job_title',
        'reason_for_leaving',
        'medical_info',
        'allergies',
        'blood_group',
        'insurance_policy_number',
        'insurance_provider',
        'insurance_expiry_date',
        'tax_id',
        'tax_residency',
        'work_permit_number',
        'work_permit_expiry_date',
        'passport_number',
        'passport_expiry_date',
        'visa_type',
        'visa_number',
        'visa_expiry_date',
        'national_id_number',
        'driving_license_number',
        'driving_license_expiry_date',
        'professional_license_number',
        'professional_license_expiry_date',
        'background_check_status',
        'background_check_date',
        'reference_check_status',
        'reference_check_date',
        'nda_signed',
        'nda_signed_date',
        'handbook_acknowledged',
        'handbook_acknowledged_date',
        'safety_training_completed',
        'safety_training_date',
        'it_policy_accepted',
        'it_policy_accepted_date',
        'security_clearance_level',
        'security_clearance_expiry_date',
        'access_card_number',
        'access_card_issued_date',
        'access_card_expiry_date',
        'parking_permit_number',
        'parking_permit_expiry_date',
        'uniform_size',
        'uniform_issued_date',
        'equipment_assigned',
        'equipment_returned_date',
        'company_email',
        'company_phone',
        'company_mobile',
        'extension',
        'fax',
        'linkedin_profile',
        'twitter_profile',
        'personal_website',
        'github_profile',
        'portfolio_url',
        'certifications',
        'awards',
        'publications',
        'languages_known',
        'hobbies',
        'interests',
        'volunteer_work',
        'achievements',
        'goals',
        'career_objectives',
        'additional_notes',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'date_of_joining' => 'date',
            'date_of_leaving' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'license_expiry_date' => 'date',
            'last_appraisal_date' => 'date',
            'next_appraisal_date' => 'date',
            'insurance_expiry_date' => 'date',
            'work_permit_expiry_date' => 'date',
            'passport_expiry_date' => 'date',
            'visa_expiry_date' => 'date',
            'driving_license_expiry_date' => 'date',
            'professional_license_expiry_date' => 'date',
            'background_check_date' => 'date',
            'reference_check_date' => 'date',
            'nda_signed_date' => 'date',
            'handbook_acknowledged_date' => 'date',
            'safety_training_date' => 'date',
            'it_policy_accepted_date' => 'date',
            'security_clearance_expiry_date' => 'date',
            'access_card_issued_date' => 'date',
            'access_card_expiry_date' => 'date',
            'parking_permit_expiry_date' => 'date',
            'uniform_issued_date' => 'date',
            'equipment_returned_date' => 'date',
            'is_active' => 'boolean',
            'document_collection_require' => 'boolean',
            'id_expiry_notification' => 'boolean',
            'document_expiry_notification' => 'boolean',
            'is_driver' => 'boolean',
            'is_security_guard' => 'boolean',
            'training_completed' => 'boolean',
            'overtime_eligible' => 'boolean',
            'nda_signed' => 'boolean',
            'handbook_acknowledged' => 'boolean',
            'safety_training_completed' => 'boolean',
            'it_policy_accepted' => 'boolean',
            'salary' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the department that belongs to the employee.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the designation that belongs to the employee.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    /**
     * Get the branch that belongs to the employee.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the attendance records for the employee.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(AttendanceEmployee::class, 'employee_id');
    }

    /**
     * Get the leave requests for the employee.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'employee_id');
    }

    /**
     * Get the payslips for the employee.
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(PaySlip::class, 'employee_id');
    }

    /**
     * Get the documents for the employee.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    /**
     * Get the assets assigned to the employee.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'employee_id');
    }

    /**
     * Get the performance appraisals for the employee.
     */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'employee_id');
    }

    /**
     * Get the awards for the employee.
     */
    public function awards(): HasMany
    {
        return $this->hasMany(Award::class, 'employee_id');
    }

    /**
     * Scope a query to only include active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope a query to only include employees with specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get the employee's full name with ID
     */
    public function getFullNameWithIdAttribute(): string
    {
        return "{$this->employee_id} - {$this->name}";
    }

    /**
     * Get the employee's employment duration
     */
    public function getEmploymentDurationAttribute(): string
    {
        if ($this->date_of_leaving) {
            $start = \Carbon\Carbon::parse($this->date_of_joining);
            $end = \Carbon\Carbon::parse($this->date_of_leaving);
        } else {
            $start = \Carbon\Carbon::parse($this->date_of_joining);
            $end = \Carbon\Carbon::now();
        }
        
        return $start->diffInYears($end) . ' years, ' . $start->diffInMonths($end) % 12 . ' months';
    }
}