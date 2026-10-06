<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
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
        return [
            'applicant_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'email' => ['required', 'email', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_nature' => ['required', 'string', 'max:255'],
            'ownership_type' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'number_of_employees' => ['required', 'integer', 'min:0'],
            'dti_sec_type' => ['nullable', Rule::in(['DTI', 'SEC'])],
            'dti_sec_number' => ['nullable', 'required_with:dti_sec_type', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_number.regex' => 'The contact number must be a valid PH mobile number (e.g. 09171234567).',
        ];
    }
}
