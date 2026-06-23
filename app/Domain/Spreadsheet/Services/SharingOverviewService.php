<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Models\Workbook;
use App\Domain\Spreadsheet\Models\WorkbookShare;
use App\Models\User;

class SharingOverviewService
{
    public function overview(User $user): array
    {
        $owned = Workbook::query()
            ->where('user_id', $user->id)
            ->with(['shares' => fn ($q) => $q->with(['user:id,name,email', 'sharedBy:id,name'])->latest()])
            ->withCount('shares')
            ->latest()
            ->get()
            ->map(fn (Workbook $workbook) => [
                'id' => $workbook->id,
                'name' => $workbook->name,
                'shares_count' => $workbook->shares_count,
                'shares' => $workbook->shares->map(fn (WorkbookShare $share) => [
                    'id' => $share->id,
                    'permission' => $share->permission->value,
                    'permission_label' => $share->permission->label(),
                    'user' => [
                        'id' => $share->user->id,
                        'name' => $share->user->name,
                        'email' => $share->user->email,
                    ],
                    'shared_by' => $share->sharedBy ? [
                        'id' => $share->sharedBy->id,
                        'name' => $share->sharedBy->name,
                    ] : null,
                    'created_at' => $share->created_at?->toIso8601String(),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $sharedWithMe = WorkbookShare::query()
            ->where('user_id', $user->id)
            ->with(['workbook:id,name,updated_at', 'sharedBy:id,name,email'])
            ->latest()
            ->get()
            ->map(fn (WorkbookShare $share) => [
                'id' => $share->id,
                'permission' => $share->permission->value,
                'permission_label' => $share->permission->label(),
                'can_edit' => $share->permission->canWrite(),
                'workbook' => [
                    'id' => $share->workbook->id,
                    'name' => $share->workbook->name,
                    'updated_at' => $share->workbook->updated_at?->toIso8601String(),
                ],
                'shared_by' => [
                    'id' => $share->sharedBy->id,
                    'name' => $share->sharedBy->name,
                    'email' => $share->sharedBy->email,
                ],
                'created_at' => $share->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'owned' => $owned,
            'shared_with_me' => $sharedWithMe,
        ];
    }
}
