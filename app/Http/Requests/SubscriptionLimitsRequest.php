<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant_id === null
            && $this->user()?->platformAdmin?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'custom_limits' => ['nullable', 'array'],
            'custom_limits.branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'custom_limits.users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'override_until' => ['nullable', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
