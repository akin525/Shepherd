<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class SubscriptionItem extends Model
{

    use HasFactory, Notifiable;

    protected $guarded=[];
    protected $table = "subscription_items";

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'subscription_id');
    }

}
