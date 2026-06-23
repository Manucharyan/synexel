<?php

namespace Tests\Feature;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Events\CellsUpdatedBroadcast;
use App\Domain\Spreadsheet\Models\WebhookDelivery;
use App\Domain\Spreadsheet\Models\WebhookSubscription;
use App\Domain\Spreadsheet\Models\WorkbookShare;
use App\Domain\Spreadsheet\Services\CellBatchService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MajorFeaturesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $collaborator;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->collaborator = User::factory()->create(['email' => 'collab@example.com']);
        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    private function auth(?User $user = null): array
    {
        $user ??= $this->owner;

        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_workbook_sharing_read_and_write_permissions(): void
    {
        $workbook = app(WorkbookService::class)->create($this->owner, 'Shared Book');

        $this->postJson("/api/v1/workbooks/{$workbook->id}/shares", [
            'email' => $this->collaborator->email,
            'permission' => 'read',
        ], $this->auth())->assertCreated();

        $this->getJson("/api/v1/workbooks/{$workbook->id}", $this->auth($this->collaborator))
            ->assertOk();

        $sheet = $workbook->sheets->first();
        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'blocked']]],
            $this->auth($this->collaborator),
        )->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AccessDenied->value,
            'outcome' => 'denied',
        ]);

        $share = WorkbookShare::first();
        $this->patchJson("/api/v1/workbooks/{$workbook->id}/shares/{$share->id}", [
            'permission' => 'write',
        ], $this->auth())->assertOk();

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'allowed']]],
            $this->auth($this->collaborator),
        )->assertOk();
    }

    public function test_only_owner_can_delete_workbook_and_denied_is_logged(): void
    {
        $workbook = app(WorkbookService::class)->create($this->owner, 'Delete Test');

        WorkbookShare::create([
            'workbook_id' => $workbook->id,
            'user_id' => $this->collaborator->id,
            'shared_by' => $this->owner->id,
            'permission' => SharePermission::Write,
        ]);

        $this->deleteJson("/api/v1/workbooks/{$workbook->id}", [], $this->auth($this->collaborator))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::WorkbookDeleted->value,
            'outcome' => 'denied',
        ]);

        $this->deleteJson("/api/v1/workbooks/{$workbook->id}", [], $this->auth())->assertOk();
    }

    public function test_webhook_subscription_delivery_log_and_audit(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['cells.updated'],
        ], $this->auth())->assertCreated();

        $subscription = WebhookSubscription::first();

        WebhookDelivery::create([
            'webhook_subscription_id' => $subscription->id,
            'event' => 'cells.updated',
            'payload' => ['test' => true],
            'status' => 'success',
            'response_code' => 200,
            'duration_ms' => 42,
        ]);

        $this->getJson('/api/v1/webhooks/deliveries', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.0.event', 'cells.updated');

        $this->getJson("/api/v1/webhooks/{$subscription->id}/deliveries", $this->auth())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::WebhookCreated->value,
            'outcome' => 'success',
        ]);
    }

    public function test_csv_import_and_export(): void
    {
        $csv = "name,amount\nAlice,10\nBob,20";
        $file = UploadedFile::fake()->createWithContent('data.csv', $csv);

        $response = $this->postJson('/api/v1/workbooks/import/csv', [
            'file' => $file,
            'name' => 'CSV Book',
        ], $this->auth())->assertCreated();

        $workbookId = $response->json('data.id');

        $this->get("/api/v1/workbooks/{$workbookId}/export/csv", $this->auth())
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CsvImported->value,
            'workbook_id' => $workbookId,
        ]);
    }

    public function test_google_sheets_import(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response("col1,col2\n1,2\n3,4", 200),
        ]);

        $response = $this->postJson('/api/v1/workbooks/import/google-sheets', [
            'spreadsheet_id' => 'abc123sheetid456789012',
            'name' => 'From Google',
        ], $this->auth())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::GoogleSheetsImported->value,
            'workbook_id' => $response->json('data.id'),
        ]);
    }

    public function test_hyperlink_in_cell_style(): void
    {
        $workbook = app(WorkbookService::class)->create($this->owner, 'Links');
        $sheet = $workbook->sheets->first();

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [[
                'row' => 1,
                'col' => 1,
                'value' => 'Synexel',
                'style' => ['hyperlink' => ['url' => 'https://example.com', 'display' => 'Synexel']],
            ]]],
            $this->auth(),
        )->assertOk();

        $cells = $this->getJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells?range=A1",
            $this->auth(),
        )->json('data.0');

        $this->assertEquals('https://example.com', $cells['style']['hyperlink']['url']);
    }

    public function test_presence_and_sync_endpoints(): void
    {
        $workbook = app(WorkbookService::class)->create($this->owner, 'Presence');
        $sheet = $workbook->sheets->first();

        app(CellBatchService::class)->batchUpdate($sheet, [
            ['row' => 1, 'col' => 1, 'value' => 'sync-me'],
        ]);

        $this->postJson("/api/v1/workbooks/{$workbook->id}/presence", [
            'sheet_id' => $sheet->id,
            'row' => 1,
            'col' => 1,
        ], $this->auth())->assertOk()->assertJsonPath('data.0.name', $this->owner->name);

        $this->getJson("/api/v1/workbooks/{$workbook->id}/presence", $this->auth())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/workbooks/{$workbook->id}/sync", $this->auth())
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_cells_updated_broadcast_event_is_dispatched(): void
    {
        Broadcast::fake();

        $workbook = app(WorkbookService::class)->create($this->owner, 'Broadcast');
        $sheet = $workbook->sheets->first();

        app(CellBatchService::class)->batchUpdate($sheet, [
            ['row' => 2, 'col' => 2, 'value' => 'live'],
        ]);

        Broadcast::assertDispatched(CellsUpdatedBroadcast::class);
    }

    public function test_share_audit_logs_are_recorded(): void
    {
        $workbook = app(WorkbookService::class)->create($this->owner, 'Audit Share');

        $this->postJson("/api/v1/workbooks/{$workbook->id}/shares", [
            'email' => $this->collaborator->email,
            'permission' => 'read',
        ], $this->auth())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ShareAdded->value,
            'workbook_id' => $workbook->id,
        ]);

        $share = WorkbookShare::first();
        $this->deleteJson("/api/v1/workbooks/{$workbook->id}/shares/{$share->id}", [], $this->auth())
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ShareRemoved->value,
        ]);
    }

    public function test_webhooks_page_is_accessible(): void
    {
        $this->actingAs($this->owner)
            ->get('/webhooks')
            ->assertOk()
            ->assertSee('Webhooks');
    }
}
