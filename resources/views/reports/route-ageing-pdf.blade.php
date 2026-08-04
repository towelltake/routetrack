<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Route Ageing Report</title>
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
        <h1>Route Ageing</h1>
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
                <th>Route</th>
                <th>Salesman</th>
                <th>Transaction Date</th>
                <th>Invoice No</th>
                <th>Customer Code</th>
                <th>Customer Name</th>
                <th>Credit Days</th>
                <th>1-30</th>
                <th>31-60</th>
                <th>61-90</th>
                <th>91-120</th>
                <th>Above 120</th>
                <th>PDC Amount</th>
                <th>PDC Date</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['route_label'] ?: '-' }}</td>
                    <td>{{ $row['salesman_label'] ?: '-' }}</td>
                    <td>{{ $row['transactiondate'] ?: '-' }}</td>
                    <td>{{ $row['invoicenumber'] ?: '-' }}</td>
                    <td>{{ $row['customercode'] ?: '-' }}</td>
                    <td>{{ $row['customername'] ?: '-' }}</td>
                    <td class="text-end">{{ $row['creditlimitdays'] }}</td>
                    <td class="text-end">{{ number_format((float) $row['age'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['age31'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['age61'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['age91'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['age121'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['pdcamount'], $amountPrecision) }}</td>
                    <td>{{ $row['pdcdate'] ?: '-' }}</td>
                    <td class="text-end">{{ number_format((float) $row['invoicebalance'], $amountPrecision) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="text-center">No records found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="text-end">Total</td>
                <td class="text-end">{{ number_format((float) $totals['age'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['age31'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['age61'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['age91'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['age121'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['pdcamount'], $amountPrecision) }}</td>
                <td></td>
                <td class="text-end">{{ number_format((float) $totals['invoicebalance'], $amountPrecision) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
