<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTicket;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('maintenance.view') || $request->user()->can('maintenance.create'), 403);
        $query = MaintenanceTicket::query()->with(['asset', 'reporter', 'repairs'])->latest('id');
        if (! $request->user()->can('maintenance.view')) {
            $query->where('reported_by_id', $request->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request, MaintenanceService $service): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'issue_description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,critical'],
        ]);

        return response()->json([
            'data' => $service->report(
                $request->user(),
                $data['asset_id'],
                $data['issue_description'],
                $data['priority'],
            ),
        ], 201);
    }

    public function checklist(Request $request, MaintenanceTicket $maintenanceTicket, MaintenanceService $service): JsonResponse
    {
        $data = $request->validate([
            'assessment_checklist.power_and_boot' => ['required', 'boolean'],
            'assessment_checklist.physical_damage' => ['required', 'boolean'],
            'assessment_checklist.connectivity' => ['required', 'boolean'],
            'assessment_checklist.security_and_data' => ['required', 'boolean'],
            'assessment_checklist.troubleshooting_attempted' => ['required', 'boolean'],
        ]);

        return response()->json([
            'data' => $service->updateChecklist(
                $maintenanceTicket,
                $request->user(),
                $data['assessment_checklist'],
            ),
        ]);
    }
}
