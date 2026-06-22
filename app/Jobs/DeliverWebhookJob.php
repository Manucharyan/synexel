<?php

namespace App\Jobs;

use App\Domain\Spreadsheet\Models\WebhookDelivery;
use App\Domain\Spreadsheet\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(
        public WebhookSubscription $subscription,
        public string $event,
        public array $payload,
        public ?string $deliveryId = null,
        public int $attempt = 1,
    ) {
        $this->tries = config('spreadsheet.webhook_max_attempts', 5);
        $this->deliveryId ??= (string) Str::uuid();
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(): void
    {
        $subscription = $this->subscription->fresh();

        if (! $subscription || ! $subscription->active) {
            return;
        }

        $body = json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $subscription->secret);
        $attempt = $this->attempts();

        $delivery = WebhookDelivery::create([
            'webhook_subscription_id' => $subscription->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'status' => 'pending',
            'attempt' => $attempt,
        ]);

        $start = microtime(true);

        try {
            $response = Http::timeout(config('spreadsheet.webhook_timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $this->event,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                    'X-Delivery-Id' => $this->deliveryId,
                ])
                ->withBody($body, 'application/json')
                ->post($subscription->url);

            $duration = (int) ((microtime(true) - $start) * 1000);

            $delivery->update([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 2000),
                'duration_ms' => $duration,
            ]);

            if (! $response->successful()) {
                $this->fail(new \RuntimeException("Webhook returned {$response->status()}"));
            }
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);

            $delivery->update([
                'status' => 'failed',
                'response_body' => substr($e->getMessage(), 0, 2000),
                'duration_ms' => $duration,
            ]);

            throw $e;
        }
    }
}
