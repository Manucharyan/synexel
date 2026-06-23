<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SpreadsheetSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpreadsheetSettingsController extends Controller
{
    public function __construct(private readonly SpreadsheetSettingsService $settings) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->forUser($request->user()),
        ]);
    }
}
