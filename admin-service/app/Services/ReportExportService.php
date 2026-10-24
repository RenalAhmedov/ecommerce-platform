<?php

namespace App\Services;

use App\Reports\SalesReport;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportExportService
{
    private const CSV_COLUMNS = ['period_start', 'period', 'orders', 'units', 'revenue', 'average_order_value'];

    /** @param  resource  $output */
    public function writeCsv(SalesReport $report, $output): void
    {
        fputcsv($output, self::CSV_COLUMNS, escape: '');

        foreach ($report->rows as $row) {
            fputcsv($output, [$row['period'], $report->rowLabel($row), $row['orders'], $row['units'], $row['revenue'], $row['average']], escape: '');
        }

        $totals = $report->totals;
        fputcsv($output, ['', 'TOTAL', $totals['orders'], $totals['units'], $totals['revenue'], $totals['average']], escape: '');
    }

    // Synchronous on purpose: a report has at most a few hundred rows, about a second of rendering.
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
