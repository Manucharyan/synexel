<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Models\WebhookDelivery;
use App\Domain\Spreadsheet\Models\WebhookSubscription;
use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookDeliveryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WebhookDeliveryController extends Controller
{
    public function index(Request $request, ?string $id = null): AnonymousResourceCollection
    {
        $query = WebhookDelivery::query()
            ->whereHas('subscription', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest();

        if ($id) {
            WebhookSubscription::query()
                ->where('user_id', $request->user()->id)
                ->where('id', $id)
                ->firstOrFail();

            $query->where('webhook_subscription_id', $id);
        }

        return WebhookDeliveryResource::collection(
            $query->paginate((int) $request->query('per_page', 50))
        );
    }
}
