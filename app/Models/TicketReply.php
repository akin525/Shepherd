<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'is_internal',
        'created_by',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the ticket associated with the reply.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who created the reply.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the creator of the reply.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include internal replies.
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope a query to only include external replies.
     */
    public function scopeExternal($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Scope a query to search replies by message.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('message', 'like', "%{$search}%");
    }

    /**
     * Determine if the reply is from a client.
     */
    public function isFromClient()
    {
        return $this->user && $this->user->role === 'client';
    }

    /**
     * Determine if the reply is from staff.
     */
    public function isFromStaff()
    {
        return $this->user && in_array($this->user->role, ['admin', 'employee']);
    }

    /**
     * Get the formatted message with line breaks.
     */
    public function getFormattedMessageAttribute()
    {
        return nl2br(e($this->message));
    }

    /**
     * Boot the model and set up events.
     */
    protected static function boot()
    {
        parent::boot();

        // When a reply is created, update the ticket's updated_at timestamp
        static::created(function ($reply) {
            $reply->ticket->touch();
        });
    }
}