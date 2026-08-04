<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Item Group Wise Sales Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; font-size: 10px; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { padding: 8px 14px; border: 1px solid #1f2937; background: #1f2937; color: #fff; cursor: pointer; }
        .heading { margin-bottom: 18px; }
        .heading h1 { margin: 0 0 6px; font-size: 22px; }
        .heading p { margin: 0; color: #4b5563; }
        .filter-grid, .report-table { width: 100%; border-collapse: collapse; }
        .filter-grid { margin-bottom: 18px; }
        .filter-grid td { border: 1px solid #d1d5db; padding: 8px 10px; vertical-align: top; width: 25%; }
        .filter-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
        .report-table th, .report-table td { border: 1px solid #cbd5e1; padding: 4px; font-size: 9px; }
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
        <h1>Item Group Wise Sales</h1>
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
                <th>Transaction Date</th>
                <th>Route</th>
                <th>Item Category</th>
                <th>Item Code</th>
                <th>Description</th>
                <th>UPC</th>
                <th>Sales Qty</th>
                <th>Sales @ Inv. Price</th>
                <th>G. Return Qty</th>
                <th>G. Return @ Inv. Price</th>
                <th>Damaged Return Qty</th>
                <th>Damaged Return @ Inv. Price</th>
                <th>Expired Qty</th>
                <th>Expired @ Inv. Price</th>
                <th>Free Qty</th>
                <th>Free Goods @ Std. Price</th>
                <th>Discounts</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['transactiondate'] ?: '-' }}</td>
                    <td>{{ $row['route_label'] ?: '-' }}</td>
                    <td>{{ $row['itemgroup_label'] ?: '-' }}</td>
                    <td>{{ $row['itemcode'] ?: '-' }}</td>
                    <td>{{ $row['itemdescription'] ?: '-' }}</td>
                    <td class="text-end">{{ number_format((float) ($row['upc'] ?? 0), 0) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['salesqty'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['sales_inv_price'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['returnqty'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['good_return_inv_price'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['damagedqty'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['damage_inv_price'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['expiryqty'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['expired_inv_price'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['freeqty'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['free_goods_std_price'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['discounts'] ?? 0), $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) ($row['total_amount'] ?? 0), $amountPrecision) }}</td>
                </tr>
            @empty
                <tr><td colspan="18" class="text-center">No records found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-end">Total</td>
                <td class="text-end">{{ number_format((float) ($totals['upc'] ?? 0), 0) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['salesqty'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['sales_inv_price'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['returnqty'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['good_return_inv_price'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['damagedqty'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['damage_inv_price'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['expiryqty'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['expired_inv_price'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['freeqty'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['free_goods_std_price'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['discounts'] ?? 0), $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) ($totals['total_amount'] ?? 0), $amountPrecision) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
