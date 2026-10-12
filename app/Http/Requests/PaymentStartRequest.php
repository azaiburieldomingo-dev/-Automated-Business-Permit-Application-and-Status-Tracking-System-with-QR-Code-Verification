<?php

namespace App\Http\Requests;

use App\Support\PaymentMethods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Strip the spaces GCash/Maya print inside reference numbers before validating.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('reference_number')) {
            $this->merge([
                'reference_number' => strtoupper((string) preg_replace('/[\s-]+/', '', (string) $this->input('reference_number'))),
            ]);
        }

        if ($this->filled('payer_number')) {
            $this->merge(['payer_number' => preg_replace('/[\s-]+/', '', (string) $this->input('payer_number'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'method' => ['required', 'string', Rule::in(PaymentMethods::availableKeys())],
        ];

        if (PaymentMethods::isManual()) {
            $rules += [
                'reference_number' => ['required', 'string', 'regex:/^[A-Z0-9]{6,30}$/'],
                'payer_name' => ['nullable', 'string', 'max:100'],
                'payer_number' => ['nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
                'proof' => [
                    'required',
                    'file',
                    'mimes:'.implode(',', (array) config('permit.uploads.mimes')),
                    'max:'.(int) config('permit.uploads.max_kb'),
                ],
                'confirm_amount' => ['accepted'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.in' => 'The selected payment method is unavailable.',
            'reference_number.required' => 'Enter the reference number shown on your payment receipt.',
            'reference_number.regex' => 'The reference number should only contain letters and numbers (for GCash, the 13-digit Ref No.).',
            'payer_number.regex' => 'Enter the mobile number you paid from, e.g. 09171234567.',
            'proof.required' => 'Upload a screenshot or photo of your payment receipt.',
            'proof.mimes' => 'The receipt must be a JPG, PNG, or PDF file.',
            'proof.max' => 'The receipt file must not be larger than '.round(((int) config('permit.uploads.max_kb')) / 1024).' MB.',
            'confirm_amount.accepted' => 'Please confirm that you sent the exact amount.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payer_name' => 'sender name',
            'payer_number' => 'sender mobile number',
            'proof' => 'payment receipt',
        ];
    }
}
