<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssetAssignment;
use App\Models\AssetRequest;
use App\Services\AssetAssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AssetAssignmentController extends Controller
{
    public function store(Request $request, AssetRequest $assetRequest, AssetAssignmentService $service): JsonResponse
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'handoff_method' => ['required', 'in:physical,courier'],
            'policy_notes' => ['required', 'string', 'min:5', 'max:2000'],
            'evidence' => ['required_if:handoff_method,courier', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $evidencePath = $request->file('evidence')?->store('handoff-evidence', 'local');
        $assignment = $service->assign(
            $assetRequest,
            $request->user(),
            $data['asset_id'],
            $data['handoff_method'],
            $evidencePath,
            $data['policy_notes'],
        );

        return response()->json(['data' => $assignment], 201);
    }

    public function acknowledge(Request $request, AssetAssignment $assetAssignment, AssetAssignmentService $service): JsonResponse
    {
        $data = $request->validate([
            'signature_name' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        return response()->json([
            'data' => $service->acknowledge($assetAssignment, $request->user(), $data['signature_name']),
        ]);
    }

    public function checkIn(Request $request, AssetAssignment $assetAssignment, AssetAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => $service->checkIn($assetAssignment, $request->user())]);
    }

    public function form(Request $request, AssetAssignment $assetAssignment)
    {
        abort_unless(
            $assetAssignment->assigned_to_id === $request->user()->id || $request->user()->can('assets.manage'),
            403,
        );

        return Pdf::loadView('pdf.accountability-form', [
            'assignment' => $assetAssignment->load(['asset.category', 'asset.location', 'assignee', 'assigner', 'request']),
        ])->stream("accountability-{$assetAssignment->asset->asset_tag}.pdf");
    }

    public function evidence(Request $request, AssetAssignment $assetAssignment)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        if ($assetAssignment->evidence_path === null) {
            throw ValidationException::withMessages(['evidence' => 'No handoff evidence was uploaded for this assignment.']);
        }

        return Storage::disk('local')->download($assetAssignment->evidence_path);
    }
}
