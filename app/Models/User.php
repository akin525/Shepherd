<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'email_verified_at',
        'avatar',
        'lang',
        'created_by',
        'plan',
        'plan_expire_date',
        'max_users',
        'parent_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plan_expire_date' => 'date',
        ];
    }

    /**
     * Get the employee associated with the user.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->type === 'admin' || $this->type === 'company';
    }

    /**
     * Check if user is HR
     */
    public function isHR(): bool
    {
        return $this->type === 'hr';
    }

    /**
     * Check if user is employee
     */
    public function isEmployee(): bool
    {
        return $this->type === 'employee';
    }

    /**
     * Get user role display name
     */
    public function getRoleDisplayName(): string
    {
        return match($this->type) {
            'admin' => 'Administrator',
            'company' => 'Company Admin',
            'hr' => 'HR Manager',
            'employee' => 'Employee',
            default => 'User',
        };
    }
}