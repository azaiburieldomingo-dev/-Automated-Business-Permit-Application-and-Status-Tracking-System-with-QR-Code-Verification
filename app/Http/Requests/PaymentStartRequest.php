<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $enabled = collect((array) config('permit.payment.methods'))
            ->filter(fn ($enabled): bool => (bool) $enabled)
            ->keys()
            ->all();

        return [
            'method' => ['required', 'string', Rule::in($enabled)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.in' => 'The selected payment method is unavailable.',
        ];
    }
}
