<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaystackTransaction extends Model
{
    protected $fillable = [
        'reference',
        'order_id',
        'booking_id',
        'user_id',
        'amount',
        'currency',
        'channel',
        'status',
        'gateway_response',
        'masked_phone',
        'webhook_payload',
        'expires_at',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'webhook_payload' => 'array',
        'expires_at' => 'datetime',
    ];
}
