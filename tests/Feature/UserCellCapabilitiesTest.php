<?php

namespace Tests\Feature;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCellCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $restricted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->restricted = User::factory()->create([
            'can_add_cells' => false,
            'can_delete_cells' => false,
        ]);
    }

    private function token(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_admin_can_restrict_add_and_delete_via_users_page(): void
    {
        $target = User::factory()->create([
            'can_add_cells' => true,
            'can_delete_cells' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.capabilities', $target), [
                'can_add_cells' => '0',
                'can_delete_cells' => '0',
            ])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertFalse($target->can_add_cells);
        $this->assertFalse($target->can_delete_cells);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserCapabilitiesUpdated->value,
            'target' => $target->email,
        ]);
    }

    public function test_restricted_user_cannot_add_or_delete_cells(): void
    {
        $workbook = app(WorkbookService::class)->create($this->restricted, 'Restricted');
        $sheet = $workbook->sheets->first();

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'blocked']]],
            $this->token($this->restricted),
        )->assertForbidden();

        $this->postJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells/clear",
            ['range' => 'A1:A1'],
            $this->token($this->restricted),
        )->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CellAddDenied->value,
            'outcome' => 'denied',
        ]);
    }

    public function test_user_with_delete_only_cannot_add_but_can_clear(): void
    {
        $user = User::factory()->create([
            'can_add_cells' => false,
            'can_delete_cells' => true,
        ]);

        $workbook = app(WorkbookService::class)->create($user, 'Delete only');
        $sheet = $workbook->sheets->first();

        app(\App\Domain\Spreadsheet\Services\CellBatchService::class)->batchUpdate($sheet, [
            ['row' => 1, 'col' => 1, 'value' => 'seed'],
        ]);

        $this->patchJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells",
            ['updates' => [['row' => 1, 'col' => 1, 'value' => 'new']]],
            $this->token($user),
        )->assertForbidden();

        $this->postJson(
            "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells/clear",
            ['range' => 'A1:A1'],
            $this->token($user),
        )->assertOk();
    }

    public function test_admin_users_page_shows_capability_controls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Cell permissions')
            ->assertSee('Can add')
            ->assertSee('Can delete')
            ->assertSee($user->email);
    }
}
