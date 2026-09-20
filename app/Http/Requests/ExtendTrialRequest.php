<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant_id === null
            && $this->user()?->platformAdmin?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'trial_ends_at' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
