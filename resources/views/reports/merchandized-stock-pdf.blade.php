<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merchandized Stock Report</title>
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
        <h1>Merchandized Stock</h1>
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
                <th>Visit Date</th>
                <th>Visit Time</th>
                <th>Customer Code</th>
                <th>Customer Name</th>
                <th>Item Code</th>
                <th>Item</th>
                <th>Case Price</th>
                <th>Unit Price</th>
                <th>Cut-off Qty</th>
                <th>Max Qty</th>
                <th>Shelf</th>
                <th>Store</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['route_label'] ?: '-' }}</td>
                    <td>{{ $row['visit_date'] ?: '-' }}</td>
                    <td>{{ $row['visit_time'] ?: '-' }}</td>
                    <td>{{ $row['customer_code'] ?: '-' }}</td>
                    <td>{{ $row['customer_name'] ?: '-' }}</td>
                    <td>{{ $row['item_code'] ?: '-' }}</td>
                    <td>{{ $row['item_description'] ?: '-' }}</td>
                    <td class="text-end">{{ number_format((float) $row['case_price'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['unit_price'], $amountPrecision) }}</td>
                    <td class="text-end">{{ $row['cutoff_qty'] }}</td>
                    <td class="text-end">{{ $row['max_qty'] }}</td>
                    <td>{{ $row['shelf_stock'] ?: '-' }}</td>
                    <td>{{ $row['store_stock'] ?: '-' }}</td>
                    <td>{{ $row['expiry'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="14" class="text-center">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
