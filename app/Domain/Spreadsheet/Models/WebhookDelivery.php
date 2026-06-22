<?php

namespace App\Domain\Spreadsheet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasUuids;

    protected $fillable = [
        'webhook_subscription_id',
        'event',
        'payload',
        'status',
        'response_code',
        'response_body',
        'duration_ms',
        'attempt',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
