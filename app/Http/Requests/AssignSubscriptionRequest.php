<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant_id === null
            && $this->user()?->platformAdmin?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['starts_at', 'current_period_starts_at', 'current_period_ends_at'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        $now = now('UTC');
        $this->merge([
            'status' => $this->input('status', 'trialing'),
            'starts_at' => $this->input('starts_at') ?: $now->toDateTimeString(),
            'trial_ends_at' => $this->input('trial_ends_at') ?: $now->copy()->addDays(14)->toDateTimeString(),
            'current_period_starts_at' => $this->input('current_period_starts_at') ?: $now->toDateTimeString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'expected_current_subscription_id' => ['present', 'nullable', 'integer'],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'status' => ['required', Rule::in(['trialing', 'active'])],
            'starts_at' => ['required', 'date'],
            'trial_ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'current_period_starts_at' => ['required', 'date'],
            'current_period_ends_at' => ['nullable', 'date', 'after:current_period_starts_at'],
            'custom_limits' => ['nullable', 'array'],
            'custom_limits.branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'custom_limits.users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'override_until' => ['nullable', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
