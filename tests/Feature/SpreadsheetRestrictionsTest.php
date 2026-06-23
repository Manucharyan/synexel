<?php

namespace Tests\Feature;

use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\SpreadsheetSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpreadsheetRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::User]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    private function asUser(): static
    {
        Sanctum::actingAs($this->user);

        return $this;
    }

    private function asAdmin(): static
    {
        Sanctum::actingAs($this->admin);

        return $this;
    }

    public function test_admin_can_toggle_spreadsheet_restrictions(): void
    {
        $this->actingAs($this->admin)
            ->patch('/admin/settings', [
                'block_adding' => '1',
                'block_deleting' => '1',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = app(SpreadsheetSettingsService::class);
        $this->assertTrue($settings->isBlockAddingEnabled());
        $this->assertTrue($settings->isBlockDeletingEnabled());
    }

    public function test_regular_user_blocked_from_adding_when_enabled(): void
    {
        app(SpreadsheetSettingsService::class)->setBlockAdding(true);

        $this->asUser()
            ->postJson('/api/v1/workbooks', ['name' => 'Blocked'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Adding data is currently disabled by an administrator.');

        $this->asAdmin()
            ->postJson('/api/v1/workbooks', ['name' => 'Also blocked'])
            ->assertForbidden();
    }

    public function test_regular_user_blocked_from_deleting_when_enabled(): void
    {
        app(SpreadsheetSettingsService::class)->setBlockDeleting(true);

        $workbook = app(WorkbookService::class)->create($this->user, 'Delete me');

        $this->asUser()
            ->deleteJson('/api/v1/workbooks/'.$workbook->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'Deleting data is currently disabled by an administrator.');

        $adminWorkbook = app(WorkbookService::class)->create($this->admin, 'Admin delete me');

        $this->asAdmin()
            ->deleteJson('/api/v1/workbooks/'.$adminWorkbook->id)
            ->assertForbidden();
    }

    public function test_user_can_still_edit_existing_cells_when_adding_blocked(): void
    {
        $workbook = app(WorkbookService::class)->create($this->user, 'Edit test');
        $sheet = $workbook->sheets->first();

        $this->asUser()->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'hello']]],
        )->assertOk();

        app(SpreadsheetSettingsService::class)->setBlockAdding(true);

        $this->asUser()->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'updated']]],
        )->assertOk();

        $this->asUser()->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 2, 'col' => 1, 'value' => 'new']]],
        )->assertForbidden();
    }

    public function test_settings_endpoint_returns_effective_access_for_user(): void
    {
        app(SpreadsheetSettingsService::class)->setBlockAdding(true);
        app(SpreadsheetSettingsService::class)->setBlockDeleting(true);

        $this->asUser()->getJson('/api/v1/settings/spreadsheet')
            ->assertOk()
            ->assertJsonPath('data.block_adding', true)
            ->assertJsonPath('data.block_deleting', true)
            ->assertJsonPath('data.can_add', false)
            ->assertJsonPath('data.can_delete', false);

        $this->asAdmin()->getJson('/api/v1/settings/spreadsheet')
            ->assertOk()
            ->assertJsonPath('data.can_add', false)
            ->assertJsonPath('data.can_delete', false);
    }

    public function test_user_can_still_apply_styles_when_adding_blocked(): void
    {
        $workbook = app(WorkbookService::class)->create($this->user, 'Style test');
        $sheet = $workbook->sheets->first();

        app(SpreadsheetSettingsService::class)->setBlockAdding(true);

        $this->asUser()->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => '', 'style' => ['bold' => true]]]],
        )->assertOk();
    }

    public function test_undo_respects_only_relevant_restriction(): void
    {
        $workbook = app(WorkbookService::class)->create($this->user, 'Undo test');
        $sheet = $workbook->sheets->first();

        $response = $this->asUser()->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'hello']]],
        )->assertOk();

        $operationId = $response->json('data.operation_id');

        app(SpreadsheetSettingsService::class)->setBlockAdding(true);

        $this->asUser()->postJson('/api/v1/operations/'.$operationId.'/revert')
            ->assertOk();
    }

    public function test_sort_blocked_when_adding_restricted(): void
    {
        $workbook = app(WorkbookService::class)->create($this->user, 'Sort test');
        $sheet = $workbook->sheets->first();

        $this->asUser()->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [
                ['row' => 1, 'col' => 1, 'value' => 'b'],
                ['row' => 2, 'col' => 1, 'value' => 'a'],
            ]],
        )->assertOk();

        app(SpreadsheetSettingsService::class)->setBlockAdding(true);

        $this->asUser()->postJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/sort",
            ['range' => 'A1:A2', 'column' => 1, 'order' => 'asc'],
        )->assertForbidden();
    }
}
