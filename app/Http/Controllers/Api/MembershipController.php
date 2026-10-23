<?php

namespace App\Http\Controllers\Api;

use App\Domain\Memberships\MembershipService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function renew(Request $request, Customer $customer, MembershipService $service): JsonResponse
    {
        $data = $request->validate([
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'payment_method' => ['required', 'string', 'in:cash,card,transfer'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:payments,reference'],
        ]);

        $membership = $service->renew(
            $customer,
            MembershipPlan::findOrFail($data['membership_plan_id']),
            $data['payment_method'],
            $request->user(),
            $data['reference'] ?? null,
        );

        return response()->json($membership, 201);
    }
}
