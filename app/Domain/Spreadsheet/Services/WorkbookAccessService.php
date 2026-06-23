<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Domain\Spreadsheet\Models\WorkbookShare;
use App\Exceptions\WorkbookAccessDeniedException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WorkbookAccessService
{
    public function listAccessible(User $user)
    {
        $ownedIds = Workbook::query()->where('user_id', $user->id)->pluck('id');
        $sharedIds = WorkbookShare::query()->where('user_id', $user->id)->pluck('workbook_id');

        return Workbook::query()
            ->whereIn('id', $ownedIds->merge($sharedIds)->unique())
            ->withCount('sheets')
            ->latest()
            ->get()
            ->map(function (Workbook $workbook) use ($user) {
                $workbook->setAttribute('access_permission', $this->permissionFor($user, $workbook)?->value);
                $workbook->setAttribute('is_owner', $workbook->user_id === $user->id);

                return $workbook;
            });
    }

    public function findAccessible(User $user, string $id, SharePermission $required = SharePermission::Write): Workbook
    {
        $workbook = Workbook::query()->where('id', $id)->first();

        if ($workbook === null) {
            throw (new ModelNotFoundException)->setModel(Workbook::class, [$id]);
        }

        $permission = $this->permissionFor($user, $workbook);

        if ($permission === null) {
            throw new WorkbookAccessDeniedException($workbook, $required, 'You do not have access to this workbook.');
        }

        if ($required === SharePermission::Write && ! $permission->canWrite()) {
            throw new WorkbookAccessDeniedException($workbook, $required, 'You have read-only access to this workbook.');
        }

        $workbook->setAttribute('access_permission', $permission->value);
        $workbook->setAttribute('is_owner', $workbook->user_id === $user->id);

        return $workbook;
    }

    public function permissionFor(User $user, Workbook $workbook): ?SharePermission
    {
        if ($workbook->user_id === $user->id) {
            return SharePermission::Write;
        }

        $share = WorkbookShare::query()
            ->where('workbook_id', $workbook->id)
            ->where('user_id', $user->id)
            ->first();

        return $share?->permission;
    }

    public function isOwner(User $user, Workbook $workbook): bool
    {
        return $workbook->user_id === $user->id;
    }
}
