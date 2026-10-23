<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use App\Models\PolicyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsurerCallbackController extends Controller
{
    /**
     * POST /api/insurer/callback
     *
     * Handles callbacks from the insurer (webhook simulation).
     *
     * Reliability Scenario — Duplicate Callbacks (requirement #8):
     *   If the insurer sends the same callback twice (network retry), we must not
     *   create duplicate policy records. We check if policy_number already exists
     *   and return 200 OK without re-processing.
     *
     * Status Regression Prevention (requirement #9):
     *   Once a policy is 'issued' or 'failed', it cannot revert to an earlier state.
     */
    public function handle(Request $request): JsonResponse
    {
        $request->validate([
            'policy_request_id' => ['required', 'integer', 'exists:policy_requests,id'],
            'policy_number'     => ['required', 'string'],
            'status'            => ['required', 'in:issued,failed'],
        ]);

        $policyRequest = PolicyRequest::findOrFail($request->policy_request_id);

        // ── Duplicate Callback: already in terminal state ─────────────────────
        if (in_array($policyRequest->status, ['issued', 'failed'])) {
            return response()->json([
                'message'      => "Callback already processed. Policy is '{$policyRequest->status}'.",
                'is_duplicate' => true,
                'data'         => $policyRequest->toApiArray(),
            ], 200);
        }

        // ── Status Regression Guard ───────────────────────────────────────────
        if (!$policyRequest->canTransitionTo($request->status)) {
            return response()->json([
                'message'        => "Invalid status transition from '{$policyRequest->status}' to '{$request->status}'.",
                'current_status' => $policyRequest->status,
            ], 422);
        }

        if ($request->status === 'issued') {
            // Duplicate policy number protection (requirement #9)
            $existing = Policy::where('policy_number', $request->policy_number)->first();
            if ($existing) {
                return response()->json([
                    'message'      => 'Policy number already exists.',
                    'is_duplicate' => true,
                    'policy_number' => $request->policy_number,
                ], 200);
            }

            Policy::create([
                'policy_request_id' => $policyRequest->id,
                'policy_number'     => $request->policy_number,
                'issued_at'         => now(),
                'status'            => 'active',
            ]);

            $policyRequest->update([
                'status'                => 'issued',
                'insurer_policy_number' => $request->policy_number,
            ]);
        } else {
            $policyRequest->update(['status' => 'failed']);
        }

        return response()->json([
            'message' => 'Callback processed.',
            'data'    => $policyRequest->fresh()->toApiArray(),
        ], 200);
    }
}
