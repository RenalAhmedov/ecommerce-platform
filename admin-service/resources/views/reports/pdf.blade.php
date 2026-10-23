<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->period->label() }} sales report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .summary { width: 100%; margin: 16px 0; border-collapse: collapse; }
        .summary td { width: 25%; padding: 8px; border: 1px solid #e5e7eb; }
        .summary .value { font-size: 14px; font-weight: bold; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { text-align: left; background: #f3f4f6; padding: 5px; font-size: 9px; text-transform: uppercase; color: #4b5563; }
        table.rows td { padding: 4px 5px; border-bottom: 1px solid #f3f4f6; }
        table.rows .num { text-align: right; }
        table.rows tfoot td { font-weight: bold; background: #f3f4f6; border-bottom: 0; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} · {{ $report->period->label() }} sales report</h1>
    <p class="muted">
        {{ $report->from->format('j M Y') }} – {{ $report->lastDay()->format('j M Y') }} (UTC) · paid orders that were not cancelled ·
        generated {{ $report->generatedAt->format('j M Y H:i') }} UTC
    </p>

    <table class="summary">
        <tr>
            <td><div class="muted">Revenue</div><div class="value">{{ $report->totals['revenue'] }}</div></td>
            <td><div class="muted">Orders</div><div class="value">{{ $report->totals['orders'] }}</div></td>
            <td><div class="muted">Units sold</div><div class="value">{{ $report->totals['units'] }}</div></td>
            <td><div class="muted">Average order</div><div class="value">{{ $report->totals['average'] }}</div></td>
        </tr>
    </table>

    <table class="rows">
        <thead>
            <tr>
                <th>Period</th>
                <th class="num">Orders</th>
                <th class="num">Units</th>
                <th class="num">Revenue</th>
                <th class="num">Average</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->rows as $row)
                <tr>
                    <td>{{ $report->rowLabel($row) }}</td>
                    <td class="num">{{ $row['orders'] }}</td>
                    <td class="num">{{ $row['units'] }}</td>
                    <td class="num">{{ $row['revenue'] }}</td>
                    <td class="num">{{ $row['average'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ $report->totals['orders'] }}</td>
                <td class="num">{{ $report->totals['units'] }}</td>
                <td class="num">{{ $report->totals['revenue'] }}</td>
                <td class="num">{{ $report->totals['average'] }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
