<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class Payment extends Model
{

    use HasFactory, Notifiable;

    protected $guarded=[];
    protected $table = "payments";

    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(SubscriptionItem::class, 'subscription_id','subscription_id');
    }

}
