<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Disposal;
use App\Services\DisposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DisposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('disposals.view') || $request->user()->can('disposals.create'), 403);
        $query = Disposal::query()->with(['asset', 'asset.category'])->latest('id');
        if (! $request->user()->can('disposals.view')) {
            $query->where('requested_by_id', $request->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request, DisposalService $service): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'disposal_type' => ['required', 'in:retirement,disposal,donation'],
        ]);

        return response()->json([
            'data' => $service->request($request->user(), $data['asset_id'], $data['disposal_type']),
        ], 201);
    }

    public function approveFinance(Request $request, Disposal $disposal, DisposalService $service): JsonResponse
    {
        return response()->json(['data' => $service->approveFinance($disposal, $request->user())]);
    }

    public function approveManagement(Request $request, Disposal $disposal, DisposalService $service): JsonResponse
    {
        return response()->json(['data' => $service->approveManagement($disposal, $request->user())]);
    }

    public function complete(Request $request, Disposal $disposal, DisposalService $service): JsonResponse
    {
        $data = $request->validate([
            'data_wipe_done' => ['required', 'accepted'],
            'company_files_removed' => ['required', 'accepted'],
            'certificate' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
            'recipient_or_vendor' => ['required', 'string', 'max:255'],
        ]);
        $data['certificate_path'] = $request->file('certificate')->store('destruction-certificates', 'local');
        unset($data['certificate']);

        return response()->json(['data' => $service->complete($disposal, $request->user(), $data)]);
    }

    public function certificate(Request $request, Disposal $disposal)
    {
        abort_unless($request->user()->can('disposals.view') || $request->user()->can('disposals.complete'), 403);
        if ($disposal->certificate_path === null) {
            throw ValidationException::withMessages(['certificate' => 'No destruction certificate is on file.']);
        }

        return Storage::disk('local')->download($disposal->certificate_path);
    }
}
