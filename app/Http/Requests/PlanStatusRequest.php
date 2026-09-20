<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant_id === null
            && $this->user()?->platformAdmin?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'expected_status' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
