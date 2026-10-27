<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LifecycleReview;
use App\Services\LifecycleReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifecycleReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('lifecycle.manage'), 403);

        return response()->json([
            'data' => LifecycleReview::query()
                ->with(['asset.category', 'asset.location'])
                ->where('status', 'due')
                ->orderBy('review_date')
                ->paginate(25),
        ]);
    }

    public function scan(Request $request, LifecycleReviewService $service): JsonResponse
    {
        abort_unless($request->user()->can('lifecycle.manage'), 403);

        return response()->json(['data' => $service->flagDueAssets()]);
    }
}
