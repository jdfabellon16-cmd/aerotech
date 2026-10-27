<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function assetsCsv(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Asset tag',
                'Serial number',
                'Category',
                'Model',
                'Supplier',
                'Purchase date',
                'Warranty expiry',
                'Condition',
                'Encryption',
                'Department',
                'Location',
                'Assignee',
                'Status',
            ]);

            Asset::query()
                ->with(['category', 'supplier', 'location', 'assignee', 'department'])
                ->chunkById(500, function ($assets) use ($output): void {
                    foreach ($assets as $asset) {
                        fputcsv($output, [
                            $asset->asset_tag,
                            $asset->serial_number,
                            $asset->category?->name,
                            $asset->model,
                            $asset->supplier?->name,
                            $asset->purchase_date?->toDateString(),
                            $asset->warranty_expires_at?->toDateString(),
                            $asset->condition,
                            $asset->encryption_status,
                            $asset->department?->name,
                            $asset->location?->name,
                            $asset->assignee?->name,
                            $asset->status,
                        ]);
                    }
                });

            fclose($output);
        }, 'aerotech-assets.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function assetsPdf(Request $request)
    {
        abort_unless($request->user()->can('reports.view'), 403);
        $assets = Asset::query()
            ->with(['category', 'supplier', 'location', 'assignee', 'department'])
            ->orderBy('asset_tag')
            ->get();

        return Pdf::loadView('pdf.asset-report', ['assets' => $assets])
            ->download('aerotech-assets.pdf');
    }
}
