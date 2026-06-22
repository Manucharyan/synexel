<?php

namespace App\Listeners;

use App\Domain\Spreadsheet\Enums\WebhookEvent;
use App\Domain\Spreadsheet\Events\CellsUpdated;
use App\Domain\Spreadsheet\Events\ChartChanged;
use App\Domain\Spreadsheet\Events\ConditionalFormatChanged;
use App\Domain\Spreadsheet\Events\NamedRangeChanged;
use App\Domain\Spreadsheet\Events\RangeCleared;
use App\Domain\Spreadsheet\Events\SheetCreated;
use App\Domain\Spreadsheet\Events\SheetRenamed;
use App\Domain\Spreadsheet\Events\WorkbookCreated;
use App\Domain\Spreadsheet\Events\WorkbookDeleted;
use App\Domain\Spreadsheet\Events\WorkbookExported;
use App\Domain\Spreadsheet\Events\WorkbookImported;
use App\Domain\Spreadsheet\Models\WebhookSubscription;
use App\Jobs\DeliverWebhookJob;

class DispatchWebhooks
{
    public function handleWorkbookCreated(WorkbookCreated $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::WorkbookCreated->value, [
            'workbook_id' => $event->workbook->id,
            'name' => $event->workbook->name,
        ]);
    }

    public function handleWorkbookDeleted(WorkbookDeleted $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::WorkbookDeleted->value, [
            'workbook_id' => $event->workbook->id,
            'name' => $event->workbook->name,
        ]);
    }

    public function handleSheetCreated(SheetCreated $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::SheetCreated->value, [
            'workbook_id' => $event->workbook->id,
            'sheet_id' => $event->sheet->id,
            'name' => $event->sheet->name,
        ]);
    }

    public function handleSheetRenamed(SheetRenamed $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::SheetRenamed->value, [
            'workbook_id' => $event->workbook->id,
            'sheet_id' => $event->sheet->id,
            'old_name' => $event->oldName,
            'new_name' => $event->sheet->name,
        ]);
    }

    public function handleCellsUpdated(CellsUpdated $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::CellsUpdated->value, [
            'workbook_id' => $event->workbook->id,
            'sheet_id' => $event->sheet->id,
            'operation_id' => $event->operationId,
            'changes' => $event->changes,
        ]);
    }

    public function handleRangeCleared(RangeCleared $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::RangeCleared->value, [
            'workbook_id' => $event->workbook->id,
            'sheet_id' => $event->sheet->id,
            'operation_id' => $event->operationId,
            'range' => $event->range,
            'changes' => $event->changes,
        ]);
    }

    public function handleNamedRangeChanged(NamedRangeChanged $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::NamedRangeChanged->value, [
            'workbook_id' => $event->workbook->id,
            'named_range_id' => $event->namedRange->id,
            'action' => $event->action,
            'name' => $event->namedRange->name,
        ]);
    }

    public function handleConditionalFormatChanged(ConditionalFormatChanged $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::ConditionalFormatChanged->value, [
            'workbook_id' => $event->workbook->id,
            'conditional_format_id' => $event->conditionalFormat->id,
            'action' => $event->action,
        ]);
    }

    public function handleChartChanged(ChartChanged $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::ChartChanged->value, [
            'workbook_id' => $event->workbook->id,
            'chart_id' => $event->chart->id,
            'action' => $event->action,
            'name' => $event->chart->name,
        ]);
    }

    public function handleWorkbookImported(WorkbookImported $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::WorkbookImported->value, [
            'workbook_id' => $event->workbook->id,
            'name' => $event->workbook->name,
        ]);
    }

    public function handleWorkbookExported(WorkbookExported $event): void
    {
        $this->dispatch($event->workbook->user_id, WebhookEvent::WorkbookExported->value, [
            'workbook_id' => $event->workbook->id,
            'name' => $event->workbook->name,
        ]);
    }

    private function dispatch(int $userId, string $event, array $payload): void
    {
        $subscriptions = WebhookSubscription::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->listensTo($event)) {
                DeliverWebhookJob::dispatch($subscription, $event, array_merge([
                    'event' => $event,
                    'timestamp' => now()->toIso8601String(),
                ], $payload));
            }
        }
    }
}
