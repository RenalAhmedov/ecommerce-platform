<?php

namespace App\Http\Controllers;

use App\Enums\ReportPeriod;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExportService $export,
    ) {}

    public function index(SalesReportRequest $request): View
    {
        return view('reports.index', [
            'report' => $this->reports->sales($request->period(), $request->firstDay(), $request->lastDay()),
            'periods' => ReportPeriod::cases(),
        ]);
    }

    public function csv(SalesReportRequest $request): StreamedResponse
    {
        $report = $this->reports->sales($request->period(), $request->firstDay(), $request->lastDay());

        return response()->streamDownload(function () use ($report) {
            $output = fopen('php://output', 'w');
            $this->export->writeCsv($report, $output);
            fclose($output);
        }, $this->export->filename($report, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(SalesReportRequest $request): Response
    {
        $report = $this->reports->sales($request->period(), $request->firstDay(), $request->lastDay());

        return response($this->export->pdf($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->export->filename($report, 'pdf').'"',
        ]);
    }
}
