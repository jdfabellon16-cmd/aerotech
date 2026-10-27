<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('assets.view'), 403);

        $assets = Asset::query()
            ->with(['category', 'supplier', 'location', 'assignee'])
            ->latest('id')
            ->paginate(25);

        return response()->json($assets);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        abort_unless($request->user()->can('assets.view'), 403);

        return response()->json([
            'data' => $asset->load(['category', 'supplier', 'location', 'assignee', 'audits.changedBy']),
        ]);
    }

    public function myAssets(Request $request): JsonResponse
    {
        $assets = Asset::query()
            ->where('assignee_id', $request->user()->id)
            ->with(['category', 'location'])
            ->latest('id')
            ->paginate(25);

        return response()->json($assets);
    }
}
