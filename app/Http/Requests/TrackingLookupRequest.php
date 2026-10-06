<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackingLookupRequest extends FormRequest
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
        $prefix = preg_quote((string) config('permit.tracking_prefix'), '/');

        return [
            'tracking_number' => ['required', 'string', 'regex:/^'.$prefix.'-\d{4}-\d{6}$/'],
            'verifier' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tracking_number.regex' => 'The tracking number format is invalid (e.g. '
                .config('permit.tracking_prefix').'-'.date('Y').'-000001).',
        ];
    }
}
