<?php

namespace App\Services;

use App\Domain\Spreadsheet\DTOs\CellUpdate;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\Sheet;
use App\Exceptions\EditingBlockedException;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

class SpreadsheetSettingsService
{
    public const KEY_BLOCK_ADDING = 'spreadsheet.block_adding';

    public const KEY_BLOCK_DELETING = 'spreadsheet.block_deleting';

    public function isBlockAddingEnabled(): bool
    {
        return $this->getBool(self::KEY_BLOCK_ADDING);
    }

    public function isBlockDeletingEnabled(): bool
    {
        return $this->getBool(self::KEY_BLOCK_DELETING);
    }

    public function setBlockAdding(bool $enabled): void
    {
        $this->setBool(self::KEY_BLOCK_ADDING, $enabled);
    }

    public function setBlockDeleting(bool $enabled): void
    {
        $this->setBool(self::KEY_BLOCK_DELETING, $enabled);
    }

    public function canAdd(?User $user): bool
    {
        if (! $this->isBlockAddingEnabled()) {
            return true;
        }

        return $user?->isAdmin() ?? false;
    }

    public function canDelete(?User $user): bool
    {
        if (! $this->isBlockDeletingEnabled()) {
            return true;
        }

        return $user?->isAdmin() ?? false;
    }

    public function assertCanAdd(?User $user): void
    {
        if (! $this->canAdd($user)) {
            throw new EditingBlockedException('Adding data is currently disabled by an administrator.');
        }
    }

    public function assertCanDelete(?User $user): void
    {
        if (! $this->canDelete($user)) {
            throw new EditingBlockedException('Deleting data is currently disabled by an administrator.');
        }
    }

    /**
     * @param  array<int, CellUpdate>  $updates
     */
    public function assertCellUpdatesAllowed(?User $user, Sheet $sheet, array $updates): void
    {
        if ($this->canAdd($user) && $this->canDelete($user)) {
            return;
        }

        $hasDelete = false;
        $addPositions = [];

        foreach ($updates as $update) {
            if ($update->clear) {
                $hasDelete = true;
                continue;
            }

            if ($this->isDataAddition($update)) {
                $addPositions[] = ['row' => $update->row, 'col' => $update->col];
            }
        }

        if ($hasDelete) {
            $this->assertCanDelete($user);
        }

        if ($addPositions === [] || $this->canAdd($user)) {
            return;
        }

        $existingCells = Cell::query()
            ->where('sheet_id', $sheet->id)
            ->where(function ($query) use ($addPositions) {
                foreach ($addPositions as $position) {
                    $query->orWhere(function ($inner) use ($position) {
                        $inner->where('row', $position['row'])->where('col', $position['col']);
                    });
                }
            })
            ->get()
            ->keyBy(fn (Cell $cell) => $cell->row.':'.$cell->col);

        foreach ($updates as $update) {
            if ($update->clear || ! $this->isDataAddition($update)) {
                continue;
            }

            $existing = $existingCells->get($update->row.':'.$update->col);

            if ($existing === null) {
                $this->assertCanAdd($user);

                return;
            }

            if ($existing->formula === null && ($existing->raw_value === null || $existing->raw_value === '')) {
                $this->assertCanAdd($user);

                return;
            }
        }
    }

    /**
     * @param  Collection<int, \App\Domain\Spreadsheet\Models\CellChange>  $changes
     */
    public function assertRevertAllowed(?User $user, Collection $changes): void
    {
        if ($this->canAdd($user) && $this->canDelete($user)) {
            return;
        }

        foreach ($changes as $change) {
            if ($change->before === null) {
                $this->assertCanDelete($user);
            } elseif ($change->after === null) {
                $this->assertCanAdd($user);
            }
        }
    }

    private function isDataAddition(CellUpdate $update): bool
    {
        return $update->formula !== null || ($update->value !== null && $update->value !== '');
    }

    /**
     * @return array{block_adding: bool, block_deleting: bool, can_add: bool, can_delete: bool}
     */
    public function forUser(?User $user): array
    {
        return [
            'block_adding' => $this->isBlockAddingEnabled(),
            'block_deleting' => $this->isBlockDeletingEnabled(),
            'can_add' => $this->canAdd($user),
            'can_delete' => $this->canDelete($user),
        ];
    }

    private function getBool(string $key): bool
    {
        $setting = Setting::query()->find($key);

        return $setting !== null && filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
    }

    private function setBool(string $key, bool $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value ? '1' : '0'],
        );
    }
}
