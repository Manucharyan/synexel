<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SpreadsheetSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpreadsheetSettingsController extends Controller
{
    public function __construct(private readonly SpreadsheetSettingsService $settings) {}

    public function index(Request $request): View
    {
        return view('admin.settings.index', [
            'blockAdding' => $this->settings->isBlockAddingEnabled(),
            'blockDeleting' => $this->settings->isBlockDeletingEnabled(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'block_adding' => ['nullable', 'boolean'],
            'block_deleting' => ['nullable', 'boolean'],
        ]);

        $this->settings->setBlockAdding($request->boolean('block_adding'));
        $this->settings->setBlockDeleting($request->boolean('block_deleting'));

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Spreadsheet restrictions updated.');
    }
}
