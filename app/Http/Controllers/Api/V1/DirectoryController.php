<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('assets.manage') || $request->user()->can('transfers.create'),
            403,
        );

        return response()->json([
            'data' => User::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'department_id']),
        ]);
    }

    public function locations(): JsonResponse
    {
        return response()->json([
            'data' => Location::query()->orderBy('name')->get(['id', 'name', 'code', 'type']),
        ]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchase_orders.manage'), 403);

        return response()->json([
            'data' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
