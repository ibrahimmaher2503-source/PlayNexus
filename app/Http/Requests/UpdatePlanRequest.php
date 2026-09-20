<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant_id === null
            && $this->user()?->platformAdmin?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? strtoupper(trim($this->input('code'))) : $this->input('code'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
            'limits' => [
                'branches' => $this->nullableInteger($this->input('branches_limit', data_get($this->input('limits'), 'branches'))),
                'users' => $this->nullableInteger($this->input('users_limit', data_get($this->input('limits'), 'users'))),
            ],
            'monthly_price_minor' => $this->minorInput('monthly_price_minor', 'monthly_price'),
            'annual_price_minor' => $this->minorInput('annual_price_minor', 'annual_price'),
        ]);
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function minorInput(string $minorKey, string $decimalKey): mixed
    {
        if ($this->has($minorKey)) {
            return $this->input($minorKey);
        }

        $value = $this->input($decimalKey);
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) === 1
            ? $this->toMinor((string) $value)
            : $value;
    }

    private function toMinor(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->getKey() ?? $this->route('plan');

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/\A[A-Z0-9][A-Z0-9_-]*\z/', Rule::unique('plans', 'code')->ignore($planId)],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'monthly_price_minor' => ['required', 'integer', 'min:0'],
            'annual_price_minor' => ['required', 'integer', 'min:0'],
            'annual_discount_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'limits' => ['required', 'array'],
            'limits.branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limits.users' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
