<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSupportAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target_tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'scope' => ['required', 'string', Rule::in(array_keys(config('platform.support_access.scopes', [])))],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:'.config('platform.support_access.max_duration_minutes', 60)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'support_ticket' => ['nullable', 'string', 'max:120'],
            'current_password' => ['required', 'string'],
        ];
    }
}
