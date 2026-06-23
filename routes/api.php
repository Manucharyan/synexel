<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CellController;
use App\Http\Controllers\Api\V1\ChartController;
use App\Http\Controllers\Api\V1\ConditionalFormatController;
use App\Http\Controllers\Api\V1\ImportExportController;
use App\Http\Controllers\Api\V1\NamedRangeController;
use App\Http\Controllers\Api\V1\OperationController;
use App\Http\Controllers\Api\V1\RangeController;
use App\Http\Controllers\Api\V1\SheetController;
use App\Http\Controllers\Api\V1\SpreadsheetSettingsController;
use App\Http\Controllers\Api\V1\WebhookSubscriptionController;
use App\Http\Controllers\Api\V1\WorkbookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/tokens', [AuthController::class, 'createToken']);

    Route::middleware(['auth:sanctum', 'audit.context'])->group(function () {
        Route::delete('auth/tokens', [AuthController::class, 'revokeTokens']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::get('settings/spreadsheet', [SpreadsheetSettingsController::class, 'show']);

        Route::get('workbooks', [WorkbookController::class, 'index']);
        Route::post('workbooks', [WorkbookController::class, 'store']);
        Route::get('workbooks/{id}', [WorkbookController::class, 'show']);
        Route::patch('workbooks/{id}', [WorkbookController::class, 'update']);
        Route::delete('workbooks/{id}', [WorkbookController::class, 'destroy']);
        Route::get('workbooks/{id}/history', [WorkbookController::class, 'history']);

        Route::post('workbooks/import', [ImportExportController::class, 'import'])->middleware('throttle:10,1');
        Route::get('workbooks/{id}/export', [ImportExportController::class, 'export'])->middleware('throttle:10,1');

        Route::post('workbooks/{workbookId}/sheets', [SheetController::class, 'store']);
        Route::get('workbooks/{workbookId}/sheets/{sheetId}', [SheetController::class, 'show']);
        Route::patch('workbooks/{workbookId}/sheets/{sheetId}', [SheetController::class, 'update']);
        Route::delete('workbooks/{workbookId}/sheets/{sheetId}', [SheetController::class, 'destroy']);

        Route::get('workbooks/{workbookId}/sheets/{sheetId}/cells', [CellController::class, 'index']);
        Route::patch('workbooks/{workbookId}/sheets/{sheetId}/cells', [CellController::class, 'update']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/cells/clear', [CellController::class, 'clear']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/cells/copy', [CellController::class, 'copy']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/import', [ImportExportController::class, 'importSheet'])->middleware('throttle:10,1');

        Route::post('workbooks/{workbookId}/sheets/{sheetId}/sort', [RangeController::class, 'sort']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/filter', [RangeController::class, 'filter']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/rows/delete', [RangeController::class, 'deleteRows']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/rows/insert', [RangeController::class, 'insertRows']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/columns/delete', [RangeController::class, 'deleteColumns']);
        Route::post('workbooks/{workbookId}/sheets/{sheetId}/columns/insert', [RangeController::class, 'insertColumns']);

        Route::get('workbooks/{workbookId}/named-ranges', [NamedRangeController::class, 'index']);
        Route::post('workbooks/{workbookId}/named-ranges', [NamedRangeController::class, 'store']);
        Route::patch('workbooks/{workbookId}/named-ranges/{id}', [NamedRangeController::class, 'update']);
        Route::delete('workbooks/{workbookId}/named-ranges/{id}', [NamedRangeController::class, 'destroy']);

        Route::get('workbooks/{workbookId}/conditional-formats', [ConditionalFormatController::class, 'index']);
        Route::post('workbooks/{workbookId}/conditional-formats', [ConditionalFormatController::class, 'store']);
        Route::patch('workbooks/{workbookId}/conditional-formats/{id}', [ConditionalFormatController::class, 'update']);
        Route::delete('workbooks/{workbookId}/conditional-formats/{id}', [ConditionalFormatController::class, 'destroy']);

        Route::get('workbooks/{workbookId}/charts', [ChartController::class, 'index']);
        Route::post('workbooks/{workbookId}/charts', [ChartController::class, 'store']);
        Route::patch('workbooks/{workbookId}/charts/{id}', [ChartController::class, 'update']);
        Route::delete('workbooks/{workbookId}/charts/{id}', [ChartController::class, 'destroy']);

        Route::post('operations/{operationId}/revert', [OperationController::class, 'revert']);

        Route::get('webhooks', [WebhookSubscriptionController::class, 'index']);
        Route::post('webhooks', [WebhookSubscriptionController::class, 'store']);
        Route::patch('webhooks/{id}', [WebhookSubscriptionController::class, 'update']);
        Route::delete('webhooks/{id}', [WebhookSubscriptionController::class, 'destroy']);
        Route::post('webhooks/{id}/test', [WebhookSubscriptionController::class, 'test']);
    });
});
