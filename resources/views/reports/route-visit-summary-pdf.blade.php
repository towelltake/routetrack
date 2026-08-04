<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Route Visit Summary Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; font-size: 12px; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { padding: 8px 14px; border: 1px solid #1f2937; background: #1f2937; color: #fff; cursor: pointer; }
        .heading { margin-bottom: 18px; }
        .heading h1 { margin: 0 0 6px; font-size: 22px; }
        .heading p { margin: 0; color: #4b5563; }
        .filter-grid, .report-table { width: 100%; border-collapse: collapse; }
        .filter-grid { margin-bottom: 18px; }
        .filter-grid td { border: 1px solid #d1d5db; padding: 8px 10px; vertical-align: top; width: 25%; }
        .filter-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
        .report-table th, .report-table td { border: 1px solid #cbd5e1; padding: 6px 5px; font-size: 11px; }
        .report-table thead th { background: #eef2f7; text-align: center; }
        .report-table tfoot td { font-weight: 700; background: #f8fafc; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        @media print { body { margin: 12px; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>
    <div class="heading">
        <h1>Route Visit Summary</h1>
        <p>Generated on {{ now()->format('d M Y H:i') }}</p>
    </div>
    <table class="filter-grid">
        <tr>
            @foreach ($selectedFilters as $label => $value)
                <td>
                    <div class="filter-label">{{ $label }}</div>
                    <div>{{ $value ?: 'All' }}</div>
                </td>
                @if (($loop->iteration % 4) === 0 && ! $loop->last)
                    </tr><tr>
                @endif
            @endforeach
        </tr>
    </table>
    <table class="report-table">
        <thead>
            <tr>
                <th>Route</th><th>Route Start Date</th><th>Day</th><th>Route End Date</th><th>Scheduled Targets To Visit</th><th>Scheduled Targets Visited</th><th>Scheduled Targets Not Visited</th><th>Un Scheduled Targets Visited</th><th>Total Visits</th><th>Shedule Sale</th><th>Shedule No Sale</th><th>Un Shedule Sale</th><th>Un Shedule No Sale</th><th>Effective Visit</th><th>Starting Kms</th><th>Ending Kms</th><th>Total Kms Covered</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['route_label'] ?: '-' }}</td><td>{{ $row['routestartdate'] ?: '-' }}</td><td>{{ $row['daytxt'] ?: '-' }}</td><td>{{ $row['routeenddate'] ?: '-' }}</td>
                    <td class="text-end">{{ $row['targettovisit'] }}</td><td class="text-end">{{ $row['targetvisits'] }}</td><td class="text-end">{{ $row['callexceptions'] }}</td><td class="text-end">{{ $row['nontargetvisits'] }}</td><td class="text-end">{{ $row['totalvisits'] }}</td><td class="text-end">{{ $row['schedulesale'] }}</td><td class="text-end">{{ $row['schedulenosale'] }}</td><td class="text-end">{{ $row['unschedsale'] }}</td><td class="text-end">{{ $row['unschedulenosale'] }}</td><td class="text-end">{{ $row['effectivevisit'] }}</td><td class="text-end">{{ $row['startkms'] }}</td><td class="text-end">{{ $row['endkms'] }}</td><td class="text-end">{{ $row['kmscovered'] }}</td>
                </tr>
            @empty
                <tr><td colspan="17" class="text-center">No records found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-end">Total</td>
                <td class="text-end">{{ $totals['targettovisit'] }}</td><td class="text-end">{{ $totals['targetvisits'] }}</td><td class="text-end">{{ $totals['callexceptions'] }}</td><td class="text-end">{{ $totals['nontargetvisits'] }}</td><td class="text-end">{{ $totals['totalvisits'] }}</td><td class="text-end">{{ $totals['schedulesale'] }}</td><td class="text-end">{{ $totals['schedulenosale'] }}</td><td class="text-end">{{ $totals['unschedsale'] }}</td><td class="text-end">{{ $totals['unschedulenosale'] }}</td><td class="text-end">{{ $totals['effectivevisit'] }}</td><td class="text-end">{{ $totals['startkms'] }}</td><td class="text-end">{{ $totals['endkms'] }}</td><td class="text-end">{{ $totals['kmscovered'] }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
