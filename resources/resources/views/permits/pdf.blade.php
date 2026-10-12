<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Mayor's Permit — {{ $permit->permit_number }}</title>
    <style>
        /* Palette: Cadet #536978 + Bone #EAE0C6 */
        body { font-family: DejaVu Sans, sans-serif; color: #536978; margin: 0; padding: 40px; }
        .header { text-align: center; border-bottom: 4px solid #C9B98E; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { font-size: 18px; margin: 0; color: #536978; }
        .header h2 { font-size: 14px; margin: 4px 0; color: #6E828F; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin: 24px 0; letter-spacing: 2px;
                 background: #536978; color: #EAE0C6; padding: 10px 0; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.details td { padding: 7px 8px; font-size: 12px; vertical-align: top; color: #2C353B; border-bottom: 1px solid #E8E0CE; }
        table.details td.label { width: 180px; font-weight: bold; color: #536978; background: #F4EEDE; }
        .qr { position: fixed; top: 40px; right: 40px; width: 120px; height: 120px; }
        .qr img { width: 120px; height: 120px; }
        .footer { margin-top: 48px; font-size: 10px; color: #555; text-align: center; }
    </style>
</head>
<body>
    <div class="qr">
        <a href="{{ $verifyUrl }}"><img src="{{ $qrDataUri }}" alt="Verification QR"></a>
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
        Scan the QR code or visit <a href="{{ $verifyUrl }}" style="color:#36454F"><strong>{{ $verifyUrl }}</strong></a> to verify this permit's authenticity.
    </div>
</body>
</html>
