<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Services\SharingOverviewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharingController extends Controller
{
    public function __construct(private readonly SharingOverviewService $overviewService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->overviewService->overview($request->user()),
        ]);
    }
}
