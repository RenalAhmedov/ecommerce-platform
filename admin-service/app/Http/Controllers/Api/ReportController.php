<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function sales(SalesReportRequest $request): JsonResponse
    {
        $report = $this->reports->sales($request->period(), $request->firstDay(), $request->lastDay());

        return response()->json(['data' => $report->toArray()]);
    }
}
