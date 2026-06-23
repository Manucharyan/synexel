<?php

namespace App\Services;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Services\AuditLogService;
use App\Exceptions\UserCapabilityDeniedException;
use App\Models\User;

class UserCapabilitiesService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function canAddCells(User $user): bool
    {
        return $user->isAdmin() || (bool) $user->can_add_cells;
    }

    public function canDeleteCells(User $user): bool
    {
        return $user->isAdmin() || (bool) $user->can_delete_cells;
    }

    public function assertCanAdd(User $user, string $action = 'add cell data'): void
    {
        if ($this->canAddCells($user)) {
            return;
        }

        $this->logDenied($user, AuditAction::CellAddDenied, 'Adding data is disabled for your account.', $action);

        throw new UserCapabilityDeniedException('You are not allowed to add or edit cell data.');
    }

    public function assertCanDelete(User $user, string $action = 'delete cell data'): void
    {
        if ($this->canDeleteCells($user)) {
            return;
        }

        $this->logDenied($user, AuditAction::CellDeleteDenied, 'Deleting data is disabled for your account.', $action);

        throw new UserCapabilityDeniedException('You are not allowed to delete or clear cell data.');
    }

    public function assertUpdatesAllowed(User $user, array $updates): void
    {
        $hasClear = collect($updates)->contains(fn ($u) => ! empty($u['clear']));
        $hasWrite = collect($updates)->contains(fn ($u) => empty($u['clear']) && (
            array_key_exists('value', $u)
            || array_key_exists('formula', $u)
            || ! empty($u['style'])
        ));

        if ($hasClear) {
            $this->assertCanDelete($user, 'clear cells');
        }

        if ($hasWrite) {
            $this->assertCanAdd($user, 'update cells');
        }
    }

    public function updateCapabilities(User $actor, User $target, bool $canAdd, bool $canDelete): User
    {
        if ($target->isAdmin()) {
            throw new \InvalidArgumentException('Administrator capabilities cannot be restricted.');
        }

        $target->update([
            'can_add_cells' => $canAdd,
            'can_delete_cells' => $canDelete,
        ]);

        $this->auditLogService->record(
            AuditAction::UserCapabilitiesUpdated,
            'Updated cell permissions for '.$target->email.' (add: '.($canAdd ? 'on' : 'off').', delete: '.($canDelete ? 'on' : 'off').')',
            target: $target->email,
            user: $actor,
            details: [
                'user_id' => $target->id,
                'can_add_cells' => $canAdd,
                'can_delete_cells' => $canDelete,
            ],
            resourceType: 'user',
        );

        return $target->fresh();
    }

    private function logDenied(User $user, AuditAction $action, string $summary, string $attempted): void
    {
        $this->auditLogService->recordDenied(
            $action,
            $summary,
            details: ['attempted' => $attempted],
            user: $user,
            resourceType: 'user_capability',
        );
    }
}
