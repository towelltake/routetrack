<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Item History Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; font-size: 11px; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { padding: 8px 14px; border: 1px solid #1f2937; background: #1f2937; color: #fff; cursor: pointer; }
        .heading { margin-bottom: 18px; }
        .heading h1 { margin: 0 0 6px; font-size: 22px; }
        .heading p { margin: 0; color: #4b5563; }
        .filter-grid, .report-table { width: 100%; border-collapse: collapse; }
        .filter-grid { margin-bottom: 18px; }
        .filter-grid td { border: 1px solid #d1d5db; padding: 8px 10px; vertical-align: top; width: 25%; }
        .filter-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
        .report-table th, .report-table td { border: 1px solid #cbd5e1; padding: 5px 4px; font-size: 10px; }
        .report-table thead th { background: #eef2f7; text-align: center; }
        .report-table tfoot td { font-weight: 700; background: #f8fafc; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        @media print { body { margin: 10px; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save PDF</button></div>
    <div class="heading"><h1>Item History Summary</h1><p>Generated on {{ now()->format('d M Y H:i') }}</p></div>

    <table class="filter-grid">
        <tr>
            @foreach ($selectedFilters as $label => $value)
                <td><div class="filter-label">{{ $label }}</div><div>{{ $value ?: 'All' }}</div></td>
                @if (($loop->iteration % 4) === 0 && ! $loop->last)
                    </tr><tr>
                @endif
            @endforeach
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th>Trip Start Date - Trip End Date</th>
                <th>Route</th>
                <th>Group</th>
                <th>Item Code</th>
                <th>Item Description</th>
                <th>Opening Case/Unit</th>
                <th>Load Case/Unit</th>
                <th>Transfer IN Case/Unit</th>
                <th>Transfer OUT Case/Unit</th>
                <th>Sales Case/Unit</th>
                <th>Good Return Case/Unit</th>
                <th>Bad Return Case/Unit</th>
                <th>Free Case/Unit</th>
                <th>Damage Variance Case/Unit</th>
                <th>Damage Variance Value</th>
                <th>Closing Case/Unit</th>
                <th>Opening Stock Value</th>
                <th>Daily Loaded Value</th>
                <th>Truck Stock Value</th>
                <th>Closing Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['trip_label'] ?: '-' }}</td>
                    <td>{{ $row['route_label'] ?: '-' }}</td>
                    <td>{{ $row['group_label'] ?: '-' }}</td>
                    <td>{{ $row['itemcode'] ?: '-' }}</td>
                    <td>{{ $row['itemdescription'] ?: '-' }}</td>
                    <td>{{ $row['openingqty'] ?: '0/0' }}</td>
                    <td>{{ $row['loadqty'] ?: '0/0' }}</td>
                    <td>{{ $row['transferinqty'] ?: '0/0' }}</td>
                    <td>{{ $row['transferoutqty'] ?: '0/0' }}</td>
                    <td>{{ $row['saleqty'] ?: '0/0' }}</td>
                    <td>{{ $row['retqty'] ?: '0/0' }}</td>
                    <td>{{ $row['dmgqty'] ?: '0/0' }}</td>
                    <td>{{ $row['freeqty'] ?: '0/0' }}</td>
                    <td>{{ $row['damagevariancestock'] ?: '0/0' }}</td>
                    <td class="text-end">{{ number_format((float) $row['damagevariancevalue'], $amountPrecision) }}</td>
                    <td>{{ $row['vanstockqty'] ?: '0/0' }}</td>
                    <td class="text-end">{{ number_format((float) $row['openingvalue'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['loadvalue'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['truckstockvalue'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format((float) $row['endstockvalue'], $amountPrecision) }}</td>
                </tr>
            @empty
                <tr><td colspan="20" class="text-center">No records found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="14" class="text-end">Total</td>
                <td class="text-end">{{ number_format((float) $totals['damagevariancevalue'], $amountPrecision) }}</td>
                <td></td>
                <td class="text-end">{{ number_format((float) $totals['openingvalue'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['loadvalue'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['truckstockvalue'], $amountPrecision) }}</td>
                <td class="text-end">{{ number_format((float) $totals['endstockvalue'], $amountPrecision) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
