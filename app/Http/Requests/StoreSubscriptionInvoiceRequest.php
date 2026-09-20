<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant_id === null
            && $this->user()?->platformAdmin?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('amount_minor') && $this->filled('amount')) {
            $value = trim((string) $this->input('amount'));
            $this->merge([
                'amount_minor' => preg_match('/\A\d+(?:\.\d{1,2})?\z/', $value) === 1
                    ? $this->toMinor($value)
                    : $value,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:80', Rule::unique('subscription_billing_records', 'reference')],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'in:EGP'],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after:billing_period_start'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'card_terminal', 'other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    private function toMinor(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
