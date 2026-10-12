<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadManyRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate a batch of uploads keyed by requirement type id: files[<typeId>].
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mimes = implode(',', (array) config('permit.uploads.mimes'));
        $maxKb = (int) config('permit.uploads.max_kb');

        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
            'files.*.key' => [Rule::exists('requirement_types', 'id')->where('is_active', true)],
        ];
    }

    /**
     * Friendly, per-document error messages (e.g. "The Valid ID must be a file
     * of type: pdf, jpg..." instead of "files.3 ...").
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mimes = implode(', ', (array) config('permit.uploads.mimes'));
        $maxMb = round(((int) config('permit.uploads.max_kb')) / 1024, 1);

        return [
            'files.required' => 'Please choose at least one file to upload.',
            'files.min' => 'Please choose at least one file to upload.',
            'files.*.required' => 'The selected file could not be read. Please choose it again.',
            'files.*.file' => 'The upload must be a valid file.',
            'files.*.mimes' => "Each file must be one of: {$mimes}.",
            'files.*.max' => "Each file must not be larger than {$maxMb} MB.",
        ];
    }
}
