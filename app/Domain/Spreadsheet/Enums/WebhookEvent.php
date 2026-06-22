<?php

namespace App\Domain\Spreadsheet\Enums;

enum WebhookEvent: string
{
    case WorkbookCreated = 'workbook.created';
    case WorkbookDeleted = 'workbook.deleted';
    case SheetCreated = 'sheet.created';
    case SheetRenamed = 'sheet.renamed';
    case CellsUpdated = 'cells.updated';
    case RangeCleared = 'range.cleared';
    case NamedRangeChanged = 'named_range.changed';
    case ConditionalFormatChanged = 'conditional_format.changed';
    case ChartChanged = 'chart.changed';
    case WorkbookImported = 'workbook.imported';
    case WorkbookExported = 'workbook.exported';

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
