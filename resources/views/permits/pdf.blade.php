<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Mayor's Permit — {{ $permit->permit_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1e3a8a; margin: 0; padding: 40px; }
        .header { text-align: center; border-bottom: 3px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { font-size: 18px; margin: 0; }
        .header h2 { font-size: 14px; margin: 4px 0; color: #1e3a8a; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin: 24px 0; letter-spacing: 2px; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.details td { padding: 6px 8px; font-size: 12px; vertical-align: top; color: #111; }
        table.details td.label { width: 180px; font-weight: bold; color: #1e3a8a; }
        .qr { position: fixed; top: 40px; right: 40px; width: 120px; height: 120px; }
        .qr img { width: 120px; height: 120px; }
        .footer { margin-top: 48px; font-size: 10px; color: #555; text-align: center; }
    </style>
</head>
<body>
    <div class="qr">
        <img src="{{ $qrDataUri }}" alt="Verification QR">
    </div>

    <div class="header">
        <h1>{{ $municipality }}</h1>
        <h2>{{ $province }}</h2>
    </div>

    <div class="title">MAYOR'S PERMIT</div>

    <table class="details">
        <tr><td class="label">Permit Number</td><td>{{ $permit->permit_number }}</td></tr>
        <tr><td class="label">Business Name</td><td>{{ $permit->business_name }}</td></tr>
        <tr><td class="label">Owner</td><td>{{ $permit->owner_name }}</td></tr>
        <tr><td class="label">Business Address</td><td>{{ $permit->business_address }}</td></tr>
        <tr><td class="label">Nature of Business</td><td>{{ $permit->business_nature }}</td></tr>
        <tr><td class="label">Date Issued</td><td>{{ $permit->issued_at?->format('F d, Y') }}</td></tr>
        <tr><td class="label">Valid Until</td><td>{{ $permit->valid_until?->format('F d, Y') }}</td></tr>
        <tr><td class="label">Status</td><td>{{ $permit->status }}</td></tr>
    </table>

    <div class="footer">
        Scan the QR code or visit <strong>{{ $verifyUrl }}</strong> to verify this permit's authenticity.
    </div>
</body>
</html>
