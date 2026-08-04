<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Order {{ $header['invoicenumber'] ?: $header['documentnumber'] }}</title>
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
        .header-grid,
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .header-grid td,
        .meta-grid td {
            vertical-align: top;
            padding: 4px 6px;
        }
        .company-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .company-name-ar {
            font-size: 16px;
            margin-bottom: 4px;
        }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-table th,
        .order-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 5px;
            font-size: 11px;
        }
        .order-table thead th {
            background: #eef2f7;
            text-align: center;
        }
        .text-end {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            margin-top: 18px;
            width: 360px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
        }
        .totals tr:last-child td {
            font-weight: 700;
            background: #eef2f7;
        }
        .signature {
            margin-top: 18px;
        }
        .signature img {
            max-height: 140px;
            border: 1px solid #cbd5e1;
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
    @php($amountPrecision = \App\Support\AmountPrecision::get())
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print Sales Order</button>
    </div>

    <table class="header-grid">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">{{ $company['name'] ?: 'Company' }}</div>
                @if($company['arbcompanyname'])
                    <div class="company-name-ar">{{ $company['arbcompanyname'] }}</div>
                @endif
                <div>{{ $company['address'] ?: '-' }}</div>
                <div>Telephone: {{ $company['telephone'] ?: '-' }}</div>
                <div>Fax: {{ $company['fax'] ?: '-' }}</div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="section-title">Sales Order</div>
                <div><strong>Order No:</strong> {{ $header['invoicenumber'] ?: '-' }}</div>
                <div><strong>Document No:</strong> {{ $header['documentnumber'] ?: '-' }}</div>
                <div><strong>Date:</strong> {{ $header['transactiondate'] ?: '-' }}</div>
                <div><strong>Time:</strong> {{ $header['transactiontime'] ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td style="width: 50%;">
                <div class="section-title">Customer</div>
                <div><strong>Code:</strong> {{ $header['customercode'] ?: '-' }}@if($header['alternatecode']) / {{ $header['alternatecode'] }}@endif</div>
                <div><strong>Name:</strong> {{ $header['customername'] ?: '-' }}</div>
                <div><strong>Address:</strong> {{ $header['customeraddress1'] ?: '-' }}</div>
                <div><strong>Payment Term:</strong> {{ $header['paymentterm'] ?: '-' }}</div>
            </td>
            <td style="width: 50%;">
                <div class="section-title">Transaction</div>
                <div><strong>Route:</strong> {{ $header['routecode'] ?: '-' }} - {{ $header['routename'] ?: '-' }}</div>
                <div><strong>Salesman:</strong> {{ $header['salesmancode'] ?: '-' }} - {{ $header['salesmanname1'] ?: '-' }}</div>
                <div><strong>Route Start Date:</strong> {{ $header['routestartdate'] ?: '-' }}</div>
                <div><strong>DSD No:</strong> {{ $header['dsdnumber'] ?: '-' }}</div>
                <div><strong>PO No:</strong> {{ $header['ponumber'] ?: '-' }}</div>
                <div><strong>Status:</strong> {{ $header['documentvalid'] ?: '-' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div><strong>Delivery Route:</strong>
                    @if($header['orderdeliveryroutecode'])
                        {{ $header['orderdeliveryroutecode'] }} - {{ $header['deliveryroutename'] ?: '-' }}
                    @else
                        -
                    @endif
                </div>
                <div><strong>Delivery Date:</strong> {{ $header['orderdeliverydate'] ?: '-' }}</div>
            </td>
            <td>
                <div><strong>Comments:</strong> {{ $header['comments'] ?: '-' }}</div>
                <div><strong>LPO Number:</strong> {{ $header['comments2'] ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <table class="order-table">
        <thead>
            <tr>
                <th rowspan="2">Alternate Code</th>
                <th rowspan="2">Item Description</th>
                <th rowspan="2">UPC</th>
                <th colspan="4">Order</th>
                <th colspan="4">Return</th>
                <th colspan="2">Damage</th>
                <th colspan="2">Free</th>
                <th colspan="3">Promotion</th>
                <th colspan="2">Tax</th>
                <th rowspan="2">Total Amount</th>
            </tr>
            <tr>
                <th>CAS</th>
                <th>PCS</th>
                <th>Case Price</th>
                <th>Unit Price</th>
                <th>CAS</th>
                <th>PCS</th>
                <th>Case Price</th>
                <th>Unit Price</th>
                <th>CAS</th>
                <th>PCS</th>
                <th>CAS</th>
                <th>PCS</th>
                <th>CAS</th>
                <th>PCS</th>
                <th>Discount</th>
                <th>Order</th>
                <th>Return</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line['display_code'] }}</td>
                    <td>{{ $line['description'] ?: '-' }}</td>
                    <td class="text-center">{{ $line['upc'] }}</td>
                    <td class="text-end">{{ $line['salescases'] }}</td>
                    <td class="text-end">{{ $line['salespcs'] }}</td>
                    <td class="text-end">{{ number_format($line['salescaseprice'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format($line['salesprice'], $amountPrecision) }}</td>
                    <td class="text-end">{{ $line['returncases'] }}</td>
                    <td class="text-end">{{ $line['returnpcs'] }}</td>
                    <td class="text-end">{{ number_format($line['returncaseprice'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format($line['returnprice'], $amountPrecision) }}</td>
                    <td class="text-end">{{ $line['damagedcases'] }}</td>
                    <td class="text-end">{{ $line['damagedpcs'] }}</td>
                    <td class="text-end">{{ $line['freegoodcases'] }}</td>
                    <td class="text-end">{{ $line['freegoodpcs'] }}</td>
                    <td class="text-end">{{ $line['promotioncases'] }}</td>
                    <td class="text-end">{{ $line['promotionpcs'] }}</td>
                    <td class="text-end">{{ number_format($line['promoamount'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format($line['taxorder'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format($line['taxreturn'], $amountPrecision) }}</td>
                    <td class="text-end">{{ number_format($line['total_amount'], $amountPrecision) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="21" class="text-center">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total Order Amount</td>
            <td class="text-end">{{ number_format($header['totalsalesamount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Final Order Amount</td>
            <td class="text-end">{{ number_format($header['totalinvoiceamount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Total Returned Amount</td>
            <td class="text-end">{{ number_format($header['totalreturnamount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Total Damaged Amount</td>
            <td class="text-end">{{ number_format($header['totaldamagedamount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Total Free Amount</td>
            <td class="text-end">{{ number_format($header['totalfreesampleamount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Total Line Item Discount</td>
            <td class="text-end">{{ number_format($header['lineitemdiscount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Total Order Discount</td>
            <td class="text-end">{{ number_format($header['orderdiscount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Total Tax Amount</td>
            <td class="text-end">{{ number_format($header['totallineitemtax'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Promo Amount</td>
            <td class="text-end">{{ number_format($header['totalpromoamount'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>VAT</td>
            <td class="text-end">{{ number_format($header['totalvat'], $amountPrecision) }}</td>
        </tr>
        <tr>
            <td>Excise Tax</td>
            <td class="text-end">{{ number_format($header['totalexcisetax'], $amountPrecision) }}</td>
        </tr>
    </table>

    @if($header['signaturedata'])
        <div class="signature">
            <strong>Signature</strong><br>
            <img src="{{ $header['signaturedata'] }}" alt="Signature">
        </div>
    @endif
</body>
</html>
