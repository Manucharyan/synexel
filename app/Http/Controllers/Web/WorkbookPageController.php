<?php

namespace App\Http\Controllers\Web;

use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkbookPageController extends Controller
{
    public function __construct(private readonly WorkbookService $workbookService) {}

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

        return view('workbooks.index', compact('workbooks'));
    }

    public function show(Request $request, string $id): View
    {
        $this->ensureApiToken($request);
        $workbook = $this->workbookService->findForUser($request->user(), $id);
        $workbook->load('sheets');

        return view('workbooks.show', compact('workbook'));
    }
}
