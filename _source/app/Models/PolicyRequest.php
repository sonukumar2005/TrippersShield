<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PolicyRequest extends Model
{
    protected $fillable = [
        'agent_id', 'client_request_id', 'customer_name', 'customer_email',
        'customer_dob', 'destination', 'plan_type', 'start_date', 'end_date',
        'coverage_amount', 'premium_amount', 'status',
        'insurer_policy_number', 'insurer_response',
    ];

    protected $casts = [
        'customer_dob'    => 'date',
        'start_date'      => 'date',
        'end_date'        => 'date',
        'coverage_amount' => 'decimal:2',
        'premium_amount'  => 'decimal:2',
        'insurer_response' => 'array',
    ];

    // Valid status transitions — prevents regressions (requirement #9)
    public const VALID_TRANSITIONS = [
        'pending'   => ['submitted'],
        'submitted' => ['issued', 'failed'],
        'issued'    => [],  // terminal
        'failed'    => [],  // terminal
    ];

    public function policy(): HasOne
    {
        return $this->hasOne(Policy::class);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function transitionTo(string $newStatus): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            return false;
        }
        return $this->update(['status' => $newStatus]);
    }

    // Clean array for API responses
    public function toApiArray(): array
    {
        return [
            'id'                    => $this->id,
            'agent_id'              => $this->agent_id,
            'client_request_id'     => $this->client_request_id,
            'customer_name'         => $this->customer_name,
            'customer_email'        => $this->customer_email,
            'destination'           => $this->destination,
            'plan_type'             => $this->plan_type,
            'start_date'            => $this->start_date->toDateString(),
            'end_date'              => $this->end_date->toDateString(),
            'coverage_amount'       => (float) $this->coverage_amount,
            'premium_amount'        => (float) $this->premium_amount,
            'status'                => $this->status,
            'insurer_policy_number' => $this->insurer_policy_number,
            'created_at'            => $this->created_at->toIso8601String(),
        ];
    }
}
