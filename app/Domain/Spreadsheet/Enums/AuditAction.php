<?php

namespace App\Domain\Spreadsheet\Enums;

enum AuditAction: string
{
    case WorkbookCreated = 'workbook.created';
    case WorkbookUpdated = 'workbook.updated';
    case WorkbookDeleted = 'workbook.deleted';
    case WorkbookImported = 'workbook.imported';
    case WorkbookExported = 'workbook.exported';
    case SheetCreated = 'sheet.created';
    case SheetRenamed = 'sheet.renamed';
    case SheetDeleted = 'sheet.deleted';
    case SheetLayoutChanged = 'sheet.layout_changed';
    case CellsUpdated = 'cells.updated';
    case RangeCleared = 'range.cleared';
    case RangeSorted = 'range.sorted';
    case FilterApplied = 'filter.applied';
    case RowsInserted = 'rows.inserted';
    case RowsDeleted = 'rows.deleted';
    case ColumnsInserted = 'columns.inserted';
    case ColumnsDeleted = 'columns.deleted';
    case OperationReverted = 'operation.reverted';
    case NamedRangeChanged = 'named_range.changed';
    case ConditionalFormatChanged = 'conditional_format.changed';
    case ChartChanged = 'chart.changed';

    public function label(): string
    {
        return match ($this) {
            self::WorkbookCreated => 'Created workbook',
            self::WorkbookUpdated => 'Updated workbook',
            self::WorkbookDeleted => 'Deleted workbook',
            self::WorkbookImported => 'Imported workbook',
            self::WorkbookExported => 'Exported workbook',
            self::SheetCreated => 'Created sheet',
            self::SheetRenamed => 'Renamed sheet',
            self::SheetDeleted => 'Deleted sheet',
            self::SheetLayoutChanged => 'Changed sheet layout',
            self::CellsUpdated => 'Updated cells',
            self::RangeCleared => 'Cleared range',
            self::RangeSorted => 'Sorted range',
            self::FilterApplied => 'Applied filter',
            self::RowsInserted => 'Inserted rows',
            self::RowsDeleted => 'Deleted rows',
            self::ColumnsInserted => 'Inserted columns',
            self::ColumnsDeleted => 'Deleted columns',
            self::OperationReverted => 'Reverted changes',
            self::NamedRangeChanged => 'Changed named range',
            self::ConditionalFormatChanged => 'Changed conditional format',
            self::ChartChanged => 'Changed chart',
        };
    }
}
