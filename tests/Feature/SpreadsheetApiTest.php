<?php

namespace Tests\Feature;

use App\Domain\Spreadsheet\Models\WebhookDelivery;
use App\Domain\Spreadsheet\Models\WebhookSubscription;
use App\Domain\Spreadsheet\Services\CellBatchService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Jobs\DeliverWebhookJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpreadsheetApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_workbook_crud(): void
    {
        $response = $this->postJson('/api/v1/workbooks', ['name' => 'Test WB'], $this->authHeaders());
        $response->assertCreated();
        $workbookId = $response->json('data.id');

        $this->getJson('/api/v1/workbooks', $this->authHeaders())->assertOk();
        $this->getJson("/api/v1/workbooks/{$workbookId}", $this->authHeaders())->assertOk();
        $this->patchJson("/api/v1/workbooks/{$workbookId}", ['name' => 'Updated'], $this->authHeaders())->assertOk();
        $this->deleteJson("/api/v1/workbooks/{$workbookId}", [], $this->authHeaders())->assertOk();
    }

    public function test_cell_batch_update_with_formula(): void
    {
        $workbook = app(WorkbookService::class)->create($this->user, 'Formula Test');
        $sheet = $workbook->sheets->first();

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            [
                'updates' => [
                    ['row' => 1, 'col' => 1, 'value' => '10'],
                    ['row' => 2, 'col' => 1, 'formula' => '=A1*2'],
                ],
            ],
            $this->authHeaders()
        )->assertOk();

        $response = $this->getJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells?range=A1:A2",
            $this->authHeaders()
        );

        $response->assertOk();
        $cells = collect($response->json('data'));
        $this->assertEquals('20', $cells->firstWhere('row', 2)['computed']);
    }

    public function test_webhook_subscription_and_delivery(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['cells.updated'],
        ], $this->authHeaders())->assertCreated();

        $workbook = app(WorkbookService::class)->create($this->user, 'Webhook Test');
        $sheet = $workbook->sheets->first();

        app(CellBatchService::class)->batchUpdate($sheet, [
            ['row' => 1, 'col' => 1, 'value' => 'hello'],
        ]);

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_webhook_signature(): void
    {
        Http::fake(['https://example.com/hook' => Http::response('ok', 200)]);

        $subscription = WebhookSubscription::create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['cells.updated'],
            'active' => true,
        ]);

        $payload = ['event' => 'cells.updated', 'test' => true];
        $job = new DeliverWebhookJob($subscription, 'cells.updated', $payload);
        $job->handle();

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_subscription_id' => $subscription->id,
            'status' => 'delivered',
        ]);

        Http::assertSent(function ($request) use ($payload) {
            $body = json_encode($payload);
            $expected = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

            return $request->hasHeader('X-Webhook-Signature', $expected);
        });
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/workbooks')->assertUnauthorized();
    }
}
