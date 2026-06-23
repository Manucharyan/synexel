<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\DTOs\A1Notation;
use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Enums\AuditOutcome;
use App\Domain\Spreadsheet\Models\Sheet;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogService
{
    public function record(
        AuditAction $action,
        string $summary,
        ?Workbook $workbook = null,
        ?Sheet $sheet = null,
        ?string $target = null,
        ?string $operationId = null,
        array $details = [],
        ?User $user = null,
        AuditOutcome $outcome = AuditOutcome::Success,
        ?string $resourceType = null,
    ): AuditLog {
        $user ??= AuditContext::user();

        if ($user === null && $workbook?->user_id) {
            $user = User::query()->find($workbook->user_id);
        }

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'outcome' => $outcome,
            'resource_type' => $resourceType,
            'summary' => $summary,
            'workbook_id' => $workbook?->id,
            'workbook_name' => $workbook?->name,
            'sheet_id' => $sheet?->id,
            'sheet_name' => $sheet?->name,
            'target' => $target,
            'operation_id' => $operationId,
            'details' => $details ?: null,
            'ip_address' => AuditContext::ipAddress(),
            'user_agent' => AuditContext::userAgent(),
            'created_at' => now(),
        ]);
    }

    public function recordDenied(
        AuditAction $action,
        string $summary,
        ?Workbook $workbook = null,
        array $details = [],
        ?User $user = null,
        ?string $resourceType = null,
    ): AuditLog {
        return $this->record(
            $action,
            $summary,
            $workbook,
            details: $details,
            user: $user,
            outcome: AuditOutcome::Denied,
            resourceType: $resourceType,
        );
    }

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('workbook_id', function ($sub) use ($user) {
                        $sub->select('id')
                            ->from('workbooks')
                            ->where('user_id', $user->id);
                    })
                    ->orWhereIn('workbook_id', function ($sub) use ($user) {
                        $sub->select('workbook_id')
                            ->from('workbook_shares')
                            ->where('user_id', $user->id);
                    });
            })
            ->orderByDesc('created_at');

        if (! empty($filters['workbook_id'])) {
            $query->where('workbook_id', $filters['workbook_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('summary', 'like', $term)
                    ->orWhere('target', 'like', $term)
                    ->orWhere('workbook_name', 'like', $term)
                    ->orWhere('sheet_name', 'like', $term);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50));
    }

    public function summarizeCellChanges(array $changes, int $limit = 8): array
    {
        $addresses = [];
        $samples = [];

        foreach ($changes as $change) {
            $row = $change['row'] ?? null;
            $col = $change['col'] ?? null;

            if ($row === null || $col === null) {
                continue;
            }

            $address = A1Notation::fromCoordinates($row, $col);
            $addresses[] = $address;

            if (count($samples) < $limit) {
                $before = $change['before'] ?? null;
                $after = $change['after'] ?? null;
                $samples[] = [
                    'cell' => $address,
                    'from' => $this->cellPreview($before),
                    'to' => $this->cellPreview($after),
                ];
            }
        }

        return [
            'count' => count($changes),
            'cells' => $addresses,
            'samples' => $samples,
        ];
    }

    private function cellPreview(?array $snapshot): ?string
    {
        if ($snapshot === null) {
            return null;
        }

        if (! empty($snapshot['formula'])) {
            return $snapshot['formula'];
        }

        $value = $snapshot['raw_value'] ?? null;

        if ($value === null || $value === '') {
            return '(empty)';
        }

        $text = (string) $value;

        return mb_strlen($text) > 40 ? mb_substr($text, 0, 37).'…' : $text;
    }
}
