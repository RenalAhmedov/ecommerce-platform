<?php

namespace App\Services;

use App\Reports\SalesReport;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportExportService
{
    private const CSV_COLUMNS = ['period_start', 'period', 'orders', 'units', 'revenue', 'average_order_value'];

    /**
     * One row per period plus a TOTAL row; plain numbers (no currency signs)
     * so spreadsheets can sum and chart them.
     *
     * @param  resource  $output
     */
    public function writeCsv(SalesReport $report, $output): void
    {
        fputcsv($output, self::CSV_COLUMNS, escape: '');

        foreach ($report->rows as $row) {
            fputcsv($output, [$row['period'], $report->rowLabel($row), $row['orders'], $row['units'], $row['revenue'], $row['average']], escape: '');
        }

        $totals = $report->totals;
        fputcsv($output, ['', 'TOTAL', $totals['orders'], $totals['units'], $totals['revenue'], $totals['average']], escape: '');
    }

    /**
     * Rendered synchronously: a report is capped at a few hundred rows
     * (ReportPeriod::maxPeriods), which Dompdf turns into a PDF in about a
     * second, so a queued job plus polling for the file would only add waiting.
     */
    public function pdf(SalesReport $report): string
    {
        return Pdf::loadView('reports.pdf', ['report' => $report])
            ->setPaper('a4')
            // Embed only the characters used instead of the whole font (~850 KB → ~20 KB for a page).
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(SalesReport $report, string $extension): string
    {
        return "sales-{$report->period->value}-{$report->from->toDateString()}-to-{$report->lastDay()->toDateString()}.{$extension}";
    }
}
