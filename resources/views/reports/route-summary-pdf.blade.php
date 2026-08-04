<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Route Summary Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 24px;
            font-size: 12px;
        }
        .toolbar {
            margin-bottom: 16px;
        }
        .toolbar button {
            padding: 8px 14px;
            border: 1px solid #1f2937;
            background: #1f2937;
            color: #fff;
            cursor: pointer;
        }
        .heading {
            margin-bottom: 18px;
        }
        .heading h1 {
            margin: 0 0 6px;
            font-size: 22px;
        }
        .heading p {
            margin: 0;
            color: #4b5563;
        }
        .filter-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .filter-grid td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            vertical-align: top;
            width: 25%;
        }
        .filter-label {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th,
        .report-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 5px;
            font-size: 11px;
        }
        .report-table thead th {
            background: #eef2f7;
            text-align: center;
        }
        .report-table tfoot td {
            font-weight: 700;
            background: #f8fafc;
        }
        .text-end {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        @media print {
            body {
                margin: 12px;
            }
            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="heading">
        <h1>Route Summary</h1>
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
                <th>Route (Salesman)</th>
                <th>Route Start Date</th>
                <th>Route Start Time</th>
                <th>Route End Date</th>
                <th>Route End Time</th>
                <th>Total Sales Documents</th>
                <th>Total Return Documents</th>
                <th>Total Cash Sales</th>
                <th>Total GC Sales</th>
                <th>Total TC Sales</th>
                <th>Total Invoiced Amount</th>
                <th>Total Receipt Documents</th>
                <th>Total Receipt Amount</th>
                <th>Total Cash</th>
                <th>Total Cheques</th>
                <th>Total Order Amount</th>
                <th>Total Expenses</th>
                <th>Inventory Variance</th>
                <th>Cash Variance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['route_label'] ?: '-' }}</td>
                    <td>{{ $row['routestartdate'] ?: '-' }}</td>
                    <td>{{ $row['routestarttime'] ?: '-' }}</td>
                    <td>{{ $row['routeenddate'] ?: '-' }}</td>
                    <td>{{ $row['routeendtime'] ?: '-' }}</td>
                    <td class="text-end">{{ number_format((int) $row['totalinvdocuments']) }}</td>
                    <td class="text-end">{{ number_format((int) $row['totalinvretdocuments']) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalcashsales'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalgcsales'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totaltcsales'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalinvoiceamount'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((int) $row['totalardocuments']) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalacctsreceivable'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalcash'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalchecks'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalorderamount'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['totalexpenses'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['inventoryvariance'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['cashvariance'], $amountPrecision) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="19" class="text-center">No records found.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td colspan="4"></td>
                <td class="text-end">{{ number_format((int) $totals['totalinvdocuments']) }}</td>
                <td class="text-end">{{ number_format((int) $totals['totalinvretdocuments']) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalcashsales'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalgcsales'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totaltcsales'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalinvoiceamount'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((int) $totals['totalardocuments']) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalacctsreceivable'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalcash'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalchecks'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalorderamount'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['totalexpenses'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['inventoryvariance'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['cashvariance'], $amountPrecision) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
