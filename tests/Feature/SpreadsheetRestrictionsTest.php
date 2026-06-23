<?php

namespace Tests\Feature;

use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\SpreadsheetSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpreadsheetRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private string $userToken;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::User]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->userToken = $this->user->createToken('test')->plainTextToken;
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function userHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->userToken];
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->adminToken];
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

        $this->postJson('/api/v1/workbooks', ['name' => 'Blocked'], $this->userHeaders())
            ->assertForbidden()
            ->assertJsonPath('message', 'Adding data is currently disabled by an administrator.');

        $this->actingAs($this->admin)
            ->postJson('/api/v1/workbooks', ['name' => 'Allowed'], $this->adminHeaders())
            ->assertCreated();
    }

    public function test_regular_user_blocked_from_deleting_when_enabled(): void
    {
        app(SpreadsheetSettingsService::class)->setBlockDeleting(true);

        $workbook = app(WorkbookService::class)->create($this->user, 'Delete me');

        $this->deleteJson('/api/v1/workbooks/'.$workbook->id, [], $this->userHeaders())
            ->assertForbidden()
            ->assertJsonPath('message', 'Deleting data is currently disabled by an administrator.');

        $this->deleteJson('/api/v1/workbooks/'.$workbook->id, [], $this->adminHeaders())
            ->assertOk();
    }

    public function test_user_can_still_edit_existing_cells_when_adding_blocked(): void
    {
        $workbook = app(WorkbookService::class)->create($this->user, 'Edit test');
        $sheet = $workbook->sheets->first();

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'hello']]],
            $this->userHeaders(),
        )->assertOk();

        app(SpreadsheetSettingsService::class)->setBlockAdding(true);

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'updated']]],
            $this->userHeaders(),
        )->assertOk();

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 2, 'col' => 1, 'value' => 'new']]],
            $this->userHeaders(),
        )->assertForbidden();
    }

    public function test_settings_endpoint_returns_effective_access_for_user(): void
    {
        app(SpreadsheetSettingsService::class)->setBlockAdding(true);
        app(SpreadsheetSettingsService::class)->setBlockDeleting(true);

        $this->getJson('/api/v1/settings/spreadsheet', $this->userHeaders())
            ->assertOk()
            ->assertJsonPath('data.block_adding', true)
            ->assertJsonPath('data.block_deleting', true)
            ->assertJsonPath('data.can_add', false)
            ->assertJsonPath('data.can_delete', false);

        $this->getJson('/api/v1/settings/spreadsheet', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.can_add', true)
            ->assertJsonPath('data.can_delete', true);
    }
}
