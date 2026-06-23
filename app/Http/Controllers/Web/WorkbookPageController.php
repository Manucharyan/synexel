<?php

namespace App\Http\Controllers\Web;

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use App\Services\SpreadsheetSettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkbookPageController extends Controller
{
    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly SpreadsheetSettingsService $spreadsheetSettings,
    ) {}

    private function ensureApiToken(Request $request): void
    {
        if ($request->session()->has('api_token')) {
            return;
        }

        $token = $request->user()->createToken('web-ui')->plainTextToken;
        $request->session()->put('api_token', $token);
    }

    public function index(Request $request): View
    {
        $this->ensureApiToken($request);
        $workbooks = $this->workbookService->listForUser($request->user());

        return view('workbooks.index', [
            'workbooks' => $workbooks,
            'spreadsheetAccess' => $this->spreadsheetSettings->forUser($request->user()),
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $this->ensureApiToken($request);
        $workbook = $this->workbookService->findForUser($request->user(), $id, SharePermission::Read);
        $workbook->load('sheets');

        return view('workbooks.show', [
            'workbook' => $workbook,
            'spreadsheetAccess' => $this->spreadsheetSettings->forUser($request->user()),
            'accessPermission' => $workbook->access_permission ?? 'write',
            'isOwner' => (bool) ($workbook->is_owner ?? $workbook->user_id === $request->user()->id),
        ]);
    }
}
