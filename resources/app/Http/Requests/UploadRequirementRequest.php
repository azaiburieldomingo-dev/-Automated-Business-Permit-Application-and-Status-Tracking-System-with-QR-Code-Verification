<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadRequirementRequest extends FormRequest
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
        $mimes = implode(',', (array) config('permit.uploads.mimes'));
        $maxKb = (int) config('permit.uploads.max_kb');

        return [
            'file' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
            'requirement_type_id' => [
                'required',
                Rule::exists('requirement_types', 'id')->where('is_active', true),
            ],
        ];
    }
}
