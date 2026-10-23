<?php

namespace Tests\Feature;

use App\Models\Policy;
use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyRequestTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'agent_id'          => 'AGENT001',
            'client_request_id' => 'REQ-' . uniqid(),
            'customer_name'     => 'Rahul Sharma',
            'customer_email'    => 'rahul@example.com',
            'customer_dob'      => '1990-05-15',
            'destination'       => 'international',
            'plan_type'         => 'standard',
            'start_date'        => now()->addMonth()->toDateString(),
            'end_date'          => now()->addMonths(2)->toDateString(),
            'coverage_amount'   => 100000,
        ], $overrides);
    }

    // ── Validation Tests ─────────────────────────────────────────────────────

    /** @test */
    public function it_rejects_empty_request()
    {
        $this->postJson('/api/policy-requests', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors([
                 'agent_id', 'client_request_id', 'customer_name',
                 'customer_email', 'customer_dob', 'destination',
                 'plan_type', 'start_date', 'end_date', 'coverage_amount',
             ]);
    }

    /** @test */
    public function it_rejects_invalid_plan_type()
    {
        $this->postJson('/api/policy-requests', $this->validPayload(['plan_type' => 'ultra']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['plan_type']);
    }

    /** @test */
    public function it_rejects_invalid_destination()
    {
        $this->postJson('/api/policy-requests', $this->validPayload(['destination' => 'mars']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['destination']);
    }

    /** @test */
    public function it_rejects_end_date_before_start_date()
    {
        $this->postJson('/api/policy-requests', $this->validPayload([
            'start_date' => now()->addMonths(2)->toDateString(),
            'end_date'   => now()->addMonth()->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    /** @test */
    public function it_rejects_coverage_below_minimum()
    {
        $this->postJson('/api/policy-requests', $this->validPayload(['coverage_amount' => 500]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['coverage_amount']);
    }

    // ── Premium Calculation Test ──────────────────────────────────────────────

    /** @test */
    public function it_calculates_and_stores_correct_premium()
    {
        // standard × international × 30 days = 30 × $3 × 1.5 = $135.00
        $start = now()->addMonth()->toDateString();
        $end   = now()->addMonth()->addDays(30)->toDateString();

        $response = $this->postJson('/api/policy-requests', $this->validPayload([
            'plan_type'   => 'standard',
            'destination' => 'international',
            'start_date'  => $start,
            'end_date'    => $end,
        ]));

        $response->assertStatus(201);
        $this->assertEquals(135.00, $response->json('data.premium_amount'));
    }

    // ── Successful Issuance Test ──────────────────────────────────────────────

    /** @test */
    public function it_creates_policy_request_and_issues_policy()
    {
        $response = $this->postJson('/api/policy-requests', $this->validPayload());

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'status', 'premium_amount',
                         'insurer_policy_number', 'created_at',
                     ],
                 ]);

        $this->assertEquals('issued', $response->json('data.status'));
        $this->assertNotNull($response->json('data.insurer_policy_number'));
        $this->assertDatabaseHas('policies', [
            'policy_number' => $response->json('data.insurer_policy_number'),
        ]);
    }

    // ── Duplicate Intake Test (Requirement #8) ────────────────────────────────

    /** @test */
    public function duplicate_request_returns_existing_record()
    {
        $payload = $this->validPayload(['client_request_id' => 'FIXED-001']);

        $first  = $this->postJson('/api/policy-requests', $payload);
        $second = $this->postJson('/api/policy-requests', $payload);

        $first->assertStatus(201);
        $second->assertStatus(200);         // returns existing, not 201
        $this->assertTrue($second->json('is_duplicate'));
        $this->assertEquals($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('policy_requests', 1); // only one row
    }

    // ── Insurer Failure Test ──────────────────────────────────────────────────

    /** @test */
    public function insurer_rejection_results_in_failed_status()
    {
        $response = $this->postJson('/api/policy-requests', $this->validPayload([
            'client_request_id' => 'REQ_FAIL', // triggers mock insurer failure
        ]));

        $response->assertStatus(201);
        $this->assertEquals('failed', $response->json('data.status'));
        $this->assertDatabaseCount('policies', 0); // no policy created
    }

    // ── Duplicate Callback Test (Requirement #8) ──────────────────────────────

    /** @test */
    public function duplicate_callback_is_idempotent()
    {
        // Step 1: Create policy request (set to submitted only — skip auto-issuance)
        $policyRequest = PolicyRequest::create([
            'agent_id'          => 'AGENT001',
            'client_request_id' => 'CB-TEST-001',
            'customer_name'     => 'Test User',
            'customer_email'    => 'test@email.com',
            'customer_dob'      => '1990-01-01',
            'destination'       => 'domestic',
            'plan_type'         => 'basic',
            'start_date'        => now()->addMonth()->toDateString(),
            'end_date'          => now()->addMonths(2)->toDateString(),
            'coverage_amount'   => 50000,
            'premium_amount'    => 45.00,
            'status'            => 'submitted', // awaiting callback
        ]);

        $callbackPayload = [
            'policy_request_id' => $policyRequest->id,
            'policy_number'     => 'POL-DUPLICATE-123',
            'status'            => 'issued',
        ];

        // Step 2: First callback — should process and issue
        $first = $this->postJson('/api/insurer/callback', $callbackPayload);
        $first->assertStatus(200);
        $this->assertEquals('issued', PolicyRequest::find($policyRequest->id)->status);

        // Step 3: Second identical callback — should be safely ignored
        $second = $this->postJson('/api/insurer/callback', $callbackPayload);
        $second->assertStatus(200);
        $this->assertTrue($second->json('is_duplicate'));

        // Policy created only once
        $this->assertDatabaseCount('policies', 1);
    }

    // ── Status Retrieval Test ─────────────────────────────────────────────────

    /** @test */
    public function it_can_retrieve_policy_request_status()
    {
        $response = $this->postJson('/api/policy-requests', $this->validPayload());
        $id = $response->json('data.id');

        $this->getJson("/api/policy-requests/{$id}")
             ->assertStatus(200)
             ->assertJsonStructure(['data' => ['id', 'status', 'premium_amount']]);
    }
}
