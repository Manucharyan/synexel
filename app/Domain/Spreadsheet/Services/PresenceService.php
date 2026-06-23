<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Events\PresenceUpdatedBroadcast;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PresenceService
{
    private const TTL_SECONDS = 45;

    public function heartbeat(Workbook $workbook, User $user, ?string $sheetId = null, ?int $row = null, ?int $col = null): array
    {
        $key = $this->cacheKey($workbook->id);
        $viewers = Cache::get($key, []);

        $viewers[$user->id] = [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'sheet_id' => $sheetId,
            'row' => $row,
            'col' => $col,
            'last_seen' => now()->toIso8601String(),
        ];

        Cache::put($key, $viewers, self::TTL_SECONDS);

        $active = $this->prune($viewers);
        Cache::put($key, $active, self::TTL_SECONDS);

        event(new PresenceUpdatedBroadcast($workbook, array_values($active)));

        return array_values($active);
    }

    public function leave(Workbook $workbook, User $user): array
    {
        $key = $this->cacheKey($workbook->id);
        $viewers = Cache::get($key, []);
        unset($viewers[$user->id]);
        Cache::put($key, $viewers, self::TTL_SECONDS);

        $active = array_values($this->prune($viewers));
        event(new PresenceUpdatedBroadcast($workbook, $active));

        return $active;
    }

    public function list(Workbook $workbook): array
    {
        $key = $this->cacheKey($workbook->id);
        $viewers = Cache::get($key, []);
        $active = $this->prune($viewers);
        Cache::put($key, $active, self::TTL_SECONDS);

        return array_values($active);
    }

    private function prune(array $viewers): array
    {
        $cutoff = now()->subSeconds(self::TTL_SECONDS);

        return array_filter($viewers, function (array $viewer) use ($cutoff) {
            $seen = isset($viewer['last_seen']) ? \Carbon\Carbon::parse($viewer['last_seen']) : null;

            return $seen && $seen->greaterThanOrEqualTo($cutoff);
        });
    }

    private function cacheKey(string $workbookId): string
    {
        return 'workbook_presence:'.$workbookId;
    }
}
