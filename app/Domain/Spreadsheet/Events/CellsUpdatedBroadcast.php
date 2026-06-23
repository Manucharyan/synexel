<?php

namespace App\Domain\Spreadsheet\Events;

use App\Domain\Spreadsheet\Models\Sheet;
use App\Domain\Spreadsheet\Models\Workbook;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CellsUpdatedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Workbook $workbook,
        public Sheet $sheet,
        public string $operationId,
        public array $changes,
        public int $userId,
        public string $userName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workbook.'.$this->workbook->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cells.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'workbook_id' => $this->workbook->id,
            'sheet_id' => $this->sheet->id,
            'operation_id' => $this->operationId,
            'changes' => $this->changes,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
        ];
    }
}
