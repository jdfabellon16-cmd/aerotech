<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssetRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('asset_requests.view_all') || $request->user()->can('asset_requests.create'),
            403,
        );

        $query = AssetRequest::query()
            ->with(['requester', 'category', 'assignedAsset', 'assignment.asset', 'assignment.assignee'])
            ->latest('id');
        if (! $request->user()->can('asset_requests.view_all')) {
            $query->where('requested_by_id', $request->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('asset_requests.create'), 403);
        $data = $request->validate([
            'asset_category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'justification' => ['required', 'string', 'min:10', 'max:5000'],
            'urgency' => ['required', 'in:low,normal,high,critical'],
        ]);

        $assetRequest = AssetRequest::create([
            ...$data,
            'request_number' => 'AR-'.Str::upper(Str::random(10)),
            'requested_by_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return response()->json([
            'data' => $assetRequest->load(['requester', 'category']),
        ], 201);
    }
}
