<?php

namespace App\Services;

use App\Models\PolicyRequest;

/**
 * MockInsurerService
 *
 * Simulates an insurance company API locally — no real credentials needed.
 *
 * Testing hooks:
 *   - client_request_id ending in '_FAIL' → simulates insurer rejection
 *   - All other requests → policy issued successfully
 */
class MockInsurerService
{
    public function submit(PolicyRequest $policyRequest): array
    {
        // Simulate failure for test cases that want a failed policy
        if (str_ends_with($policyRequest->client_request_id, '_FAIL')) {
            return [
                'success' => false,
                'error'   => 'Insurer declined: policy criteria not met.',
            ];
        }

        // Generate a deterministic, unique policy number
        $timestamp    = now()->format('YmdHis');
        $requestId    = str_pad((string) $policyRequest->id, 6, '0', STR_PAD_LEFT);
        $policyNumber = "TPP-{$timestamp}-{$requestId}";

        return [
            'success'       => true,
            'policy_number' => $policyNumber,
            'issued_at'     => now()->toIso8601String(),
        ];
    }
}
