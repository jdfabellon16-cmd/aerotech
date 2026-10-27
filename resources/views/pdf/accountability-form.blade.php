<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Asset Accountability Form</title>
    <style>
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; }
        h1 { color: #123c78; font-size: 20px; margin-bottom: 4px; }
        h2 { border-bottom: 1px solid #cbd5e1; font-size: 13px; margin-top: 24px; padding-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px 5px; vertical-align: top; }
        .label { color: #64748b; width: 28%; }
        .signature { border-bottom: 1px solid #475569; height: 36px; width: 48%; }
        .notice { background: #eff6ff; margin-top: 18px; padding: 12px; }
    </style>
</head>
<body>
    <h1>AeroTech Solutions Inc.</h1>
    <p>IT Asset Accountability &amp; Handoff</p>
    <table>
        <tr><td class="label">Asset tag</td><td>{{ $assignment->asset->asset_tag }}</td></tr>
        <tr><td class="label">Serial number</td><td>{{ $assignment->asset->serial_number ?? 'Not recorded' }}</td></tr>
        <tr><td class="label">Category / model</td><td>{{ $assignment->asset->category->name }} — {{ $assignment->asset->model }}</td></tr>
        <tr><td class="label">Assigned to</td><td>{{ $assignment->assignee->name }} ({{ $assignment->assignee->email }})</td></tr>
        <tr><td class="label">Department / location</td><td>{{ $assignment->assignee->department?->name ?? '—' }} / {{ $assignment->asset->location?->name ?? '—' }}</td></tr>
        <tr><td class="label">Handoff method</td><td>{{ ucfirst($assignment->handoff_method) }}</td></tr>
        <tr><td class="label">Issued by / date</td><td>{{ $assignment->assigner->name }} / {{ $assignment->assigned_at?->format('F j, Y') }}</td></tr>
    </table>
    <div class="notice">I acknowledge receipt of the asset identified above and agree to use, safeguard, and return it in accordance with company IT policy.</div>
    <h2>Employee acknowledgement</h2>
    <table>
        <tr><td class="label">Printed name</td><td>{{ $assignment->signature_name ?? $assignment->assignee->name }}</td></tr>
        <tr><td class="label">Signature / digital acknowledgement</td><td class="signature"></td></tr>
        <tr><td class="label">Acknowledged at</td><td>{{ $assignment->acknowledged_at?->format('F j, Y g:i A') ?? 'Pending employee acknowledgement' }}</td></tr>
    </table>
</body>
</html>
