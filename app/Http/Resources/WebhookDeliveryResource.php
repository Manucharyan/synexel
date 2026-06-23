<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_subscription_id' => $this->webhook_subscription_id,
            'event' => $this->event,
            'status' => $this->status,
            'response_code' => $this->response_code,
            'response_body' => $this->response_body ? mb_substr($this->response_body, 0, 500) : null,
            'duration_ms' => $this->duration_ms,
            'attempt' => $this->attempt,
            'payload' => $this->payload,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
