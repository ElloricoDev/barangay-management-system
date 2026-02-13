<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $barangayName }} - Official Receipt {{ $payment->or_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #0f172a;
        }
        .receipt {
            max-width: 760px;
            margin: 0 auto;
            border: 1px solid #cbd5e1;
            padding: 24px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 4px 0;
            font-size: 13px;
            color: #475569;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .label {
            color: #334155;
            font-weight: 700;
            min-width: 180px;
        }
        .value {
            flex: 1;
            text-align: right;
        }
        .amount {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #cbd5e1;
            font-size: 18px;
            font-weight: 700;
        }
        .footer {
            margin-top: 36px;
            display: flex;
            justify-content: space-between;
            align-items: end;
            font-size: 13px;
        }
        .signature {
            text-align: right;
        }
        .signature-line {
            border-top: 1px solid #334155;
            width: 220px;
            margin-left: auto;
            margin-top: 36px;
            padding-top: 6px;
        }
        .actions {
            max-width: 760px;
            margin: 0 auto 12px auto;
            text-align: right;
        }
        .print-btn {
            background: #0f172a;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 8px 14px;
            cursor: pointer;
        }
        @media print {
            .actions {
                display: none;
            }
            body {
                margin: 0;
            }
            .receipt {
                border: 0;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="receipt">
        <div class="header">
            <h1>BARANGAY OFFICIAL RECEIPT</h1>
            <p>{{ $barangayName }}</p>
            <p>OR Number: {{ $payment->or_number }}</p>
        </div>

        <div class="row">
            <div class="label">Received From</div>
            <div class="value">
                {{ $payment->resident ? "{$payment->resident->last_name}, {$payment->resident->first_name}" : 'N/A' }}
            </div>
        </div>
        <div class="row">
            <div class="label">Service Type</div>
            <div class="value">{{ $payment->service_type }}</div>
        </div>
        <div class="row">
            <div class="label">Description</div>
            <div class="value">{{ $payment->description }}</div>
        </div>
        <div class="row">
            <div class="label">Date Paid</div>
            <div class="value">{{ optional($payment->paid_at)->format('M d, Y h:i A') }}</div>
        </div>
        <div class="row amount">
            <div class="label">Amount Paid</div>
            <div class="value">PHP {{ number_format((float) $payment->amount, 2) }}</div>
        </div>

        <div class="footer">
            <div>
                <div>Generated: {{ now()->format('M d, Y h:i A') }}</div>
            </div>
            <div class="signature">
                <div class="signature-line">{{ $payment->collector?->name ?? $issuedBy }}</div>
                <div>Authorized Collector</div>
            </div>
        </div>
    </div>
</body>
</html>
