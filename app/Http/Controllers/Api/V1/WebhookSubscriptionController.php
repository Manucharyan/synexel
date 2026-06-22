<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Enums\WebhookEvent;
use App\Domain\Spreadsheet\Models\WebhookSubscription;
use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookSubscriptionResource;
use App\Jobs\DeliverWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class WebhookSubscriptionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $subscriptions = WebhookSubscription::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return WebhookSubscriptionResource::collection($subscriptions);
    }

    public function store(Request $request): WebhookSubscriptionResource
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookEvent::all())],
            'active' => ['nullable', 'boolean'],
        ]);

        $subscription = WebhookSubscription::create([
            'user_id' => $request->user()->id,
            'url' => $data['url'],
            'secret' => Str::random(64),
            'events' => $data['events'],
            'active' => $data['active'] ?? true,
        ]);

        return new WebhookSubscriptionResource($subscription);
    }

    public function update(Request $request, string $id): WebhookSubscriptionResource
    {
        $subscription = WebhookSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookEvent::all())],
            'active' => ['sometimes', 'boolean'],
        ]);

        $subscription->update($data);

        return new WebhookSubscriptionResource($subscription->fresh());
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $subscription = WebhookSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $subscription->delete();

        return response()->json(['message' => 'Webhook subscription deleted.']);
    }

    public function test(Request $request, string $id): JsonResponse
    {
        $subscription = WebhookSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        DeliverWebhookJob::dispatch($subscription, 'webhook.test', [
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'message' => 'This is a test webhook delivery.',
        ]);

        return response()->json(['message' => 'Test webhook queued for delivery.']);
    }
}
