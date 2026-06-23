<?php

namespace App\Http\Controllers\Concerns;

use App\Services\SpreadsheetSettingsService;
use Illuminate\Http\Request;

trait ChecksSpreadsheetAccess
{
    protected function spreadsheetSettings(): SpreadsheetSettingsService
    {
        return app(SpreadsheetSettingsService::class);
    }

    protected function assertCanAddSpreadsheetData(Request $request): void
    {
        $this->spreadsheetSettings()->assertCanAdd($request->user());
    }

    protected function assertCanDeleteSpreadsheetData(Request $request): void
    {
        $this->spreadsheetSettings()->assertCanDelete($request->user());
    }
}
