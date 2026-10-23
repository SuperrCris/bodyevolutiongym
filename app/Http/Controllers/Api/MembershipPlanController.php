<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(MembershipPlan::query()->where('active', true)->orderBy('duration_days')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $plan = MembershipPlan::create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return response()->json($plan, 201);
    }
}
