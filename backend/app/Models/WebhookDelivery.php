<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'webhook_subscription_id',
    'event',
    'payload',
    'status',
    'http_status',
    'response_body',
    'error',
    'attempts',
    'delivered_at',
])]
class WebhookDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
