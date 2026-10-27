<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssetTransfer;
use App\Services\AssetTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('transfers.manage') || $request->user()->can('transfers.create'), 403);
        $query = AssetTransfer::query()->with(['asset', 'requester', 'recipient', 'destination'])->latest('id');
        if (! $request->user()->can('transfers.manage')) {
            $query->where('requested_by_id', $request->user()->id)->orWhere('to_user_id', $request->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request, AssetTransferService $service): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id'],
            'transfer_type' => ['required', 'in:permanent,temporary'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'loan_started_at' => ['required_if:transfer_type,temporary', 'nullable', 'date'],
            'due_at' => ['required_if:transfer_type,temporary', 'nullable', 'date'],
            'replacement_expected_at' => ['nullable', 'date'],
        ]);

        return response()->json(['data' => $service->request($request->user(), $data)], 201);
    }

    public function approve(Request $request, AssetTransfer $assetTransfer, AssetTransferService $service): JsonResponse
    {
        return response()->json(['data' => $service->approve($assetTransfer, $request->user())]);
    }

    public function accept(Request $request, AssetTransfer $assetTransfer, AssetTransferService $service): JsonResponse
    {
        return response()->json(['data' => $service->accept($assetTransfer, $request->user())]);
    }
}
