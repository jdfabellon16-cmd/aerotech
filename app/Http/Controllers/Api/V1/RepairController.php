<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTicket;
use App\Models\Repair;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepairController extends Controller
{
    public function store(Request $request, MaintenanceTicket $maintenanceTicket, MaintenanceService $service): JsonResponse
    {
        $data = $request->validate([
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'paid' => ['required', 'boolean'],
            'hr_cost_deduction_flag' => ['sometimes', 'boolean'],
            'repair_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json(['data' => $service->recordRepair($maintenanceTicket, $request->user(), $data)], 201);
    }

    public function approve(Request $request, Repair $repair, MaintenanceService $service): JsonResponse
    {
        $data = $request->validate(['hr_cost_deduction_flag' => ['required', 'boolean']]);

        return response()->json([
            'data' => $service->approveRepairCost($repair, $request->user(), $data['hr_cost_deduction_flag']),
        ]);
    }

    public function complete(Request $request, Repair $repair, MaintenanceService $service): JsonResponse
    {
        return response()->json(['data' => $service->completeRepair($repair, $request->user())]);
    }
}
