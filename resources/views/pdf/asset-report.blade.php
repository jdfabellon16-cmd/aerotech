<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AeroTech Asset Inventory</title>
    <style>
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { color: #123c78; font-size: 18px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #123c78; color: #fff; text-align: left; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; }
        tr:nth-child(even) { background: #f1f5f9; }
    </style>
</head>
<body>
    <h1>AeroTech Solutions Inc. — Asset Inventory</h1>
    <p>Generated {{ now()->format('F j, Y g:i A') }} · {{ $assets->count() }} assets</p>
    <table>
        <thead><tr><th>Tag / serial</th><th>Category / model</th><th>Supplier</th><th>Location / department</th><th>Assignee</th><th>Status</th><th>Warranty</th></tr></thead>
        <tbody>
        @foreach ($assets as $asset)
            <tr>
                <td>{{ $asset->asset_tag }}<br>{{ $asset->serial_number ?? '—' }}</td>
                <td>{{ $asset->category?->name }}<br>{{ $asset->model }}</td>
                <td>{{ $asset->supplier?->name ?? '—' }}</td>
                <td>{{ $asset->location?->name ?? '—' }}<br>{{ $asset->department?->name ?? '—' }}</td>
                <td>{{ $asset->assignee?->name ?? '—' }}</td>
                <td>{{ str_replace('_', ' ', ucfirst($asset->status)) }}</td>
                <td>{{ $asset->warranty_expires_at?->toDateString() ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
