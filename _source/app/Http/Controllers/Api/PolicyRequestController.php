<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePolicyRequestRequest;
use App\Models\Policy;
use App\Models\PolicyRequest;
use App\Services\MockInsurerService;
use App\Services\PremiumCalculatorService;
use Illuminate\Http\JsonResponse;

class PolicyRequestController extends Controller
{
    public function __construct(
        private PremiumCalculatorService $calculator,
        private MockInsurerService $insurer
    ) {}

    /**
     * POST /api/policy-requests
     *
     * Creates a policy request. Idempotent:
     * same (agent_id + client_request_id) returns the existing record (requirement #4 & #8).
     */
    public function store(StorePolicyRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        // ── Duplicate Intake Check ───────────────────────────────────────────
        $existing = PolicyRequest::where('agent_id', $data['agent_id'])
            ->where('client_request_id', $data['client_request_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message'      => 'Duplicate request detected. Returning existing record.',
                'is_duplicate' => true,
                'data'         => $existing->toApiArray(),
            ], 200);
        }

        // ── Calculate Premium (stored as immutable snapshot) ─────────────────
        $premium = $this->calculator->calculate(
            $data['plan_type'],
            $data['destination'],
            $data['start_date'],
            $data['end_date']
        );

        // ── Create Policy Request ─────────────────────────────────────────────
        $policyRequest = PolicyRequest::create([
            ...$data,
            'premium_amount' => $premium,
            'status'         => 'pending',
        ]);

        // ── Submit to Mock Insurer ─────────────────────────────────────────────
        $policyRequest->transitionTo('submitted');
        $result = $this->insurer->submit($policyRequest);

        if ($result['success']) {
            Policy::create([
                'policy_request_id' => $policyRequest->id,
                'policy_number'     => $result['policy_number'],
                'issued_at'         => now(),
                'status'            => 'active',
            ]);

            $policyRequest->update([
                'status'                => 'issued',
                'insurer_policy_number' => $result['policy_number'],
                'insurer_response'      => $result,
            ]);
        } else {
            $policyRequest->update([
                'status'           => 'failed',
                'insurer_response' => $result,
            ]);
        }

        return response()->json([
            'message' => 'Policy request processed.',
            'data'    => $policyRequest->fresh()->toApiArray(),
        ], 201);
    }

    /**
     * GET /api/policy-requests/{id}
     * Retrieve current status of a policy request.
     */
    public function show(PolicyRequest $policyRequest): JsonResponse
    {
        return response()->json(['data' => $policyRequest->toApiArray()]);
    }
}
