<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContact extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'client_contacts';

//    protected $fillable = [
//        'client_id',
//        'name',
//        'email',
//        'phone',
//        'position',
//        'department',
//        'is_primary',
//        'notes',
//        'created_by',
//        'updated_by',
//    ];


    /**
     * Get the client associated with the contact.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created the contact.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the contact.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include primary contacts.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to search contacts by name or email.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('position', 'like', "%{$search}%");
        });
    }

    /**
     * Boot the model and set up events.
     */
    protected static function boot()
    {
        parent::boot();

        // When a contact is set as primary, unset other primary contacts for the same client
        static::saving(function ($contact) {
            if ($contact->is_primary) {
                static::where('client_id', $contact->client_id)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }
        });
    }
}
