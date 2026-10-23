<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePolicyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // No auth for this assignment
    }

    public function rules(): array
    {
        return [
            'agent_id'          => ['required', 'string', 'max:50'],
            'client_request_id' => ['required', 'string', 'max:100'],
            'customer_name'     => ['required', 'string', 'max:255'],
            'customer_email'    => ['required', 'email', 'max:255'],
            'customer_dob'      => ['required', 'date', 'before:' . now()->subYears(18)->toDateString()],
            'destination'       => ['required', 'in:domestic,international,schengen'],
            'plan_type'         => ['required', 'in:basic,standard,premium'],
            'start_date'        => ['required', 'date', 'after_or_equal:today'],
            'end_date'          => ['required', 'date', 'after:start_date'],
            'coverage_amount'   => ['required', 'numeric', 'min:10000', 'max:10000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_dob.before'       => 'Customer must be at least 18 years old.',
            'destination.in'            => 'Destination must be: domestic, international, or schengen.',
            'plan_type.in'              => 'Plan must be: basic, standard, or premium.',
            'start_date.after_or_equal' => 'Start date must be today or a future date.',
            'end_date.after'            => 'End date must be after start date.',
            'coverage_amount.min'       => 'Minimum coverage is $10,000.',
            'coverage_amount.max'       => 'Maximum coverage is $10,000,000.',
        ];
    }
}
