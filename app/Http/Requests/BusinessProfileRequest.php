<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'nature' => ['required', 'string', 'max:255'],
            'ownership_type' => ['required', 'string', 'max:255'],
            'dti_sec_type' => ['nullable', Rule::in(['DTI', 'SEC'])],
            'dti_sec_number' => ['nullable', 'required_with:dti_sec_type', 'string', 'max:255'],
            'number_of_employees' => ['required', 'integer', 'min:0'],
        ];
    }
}
