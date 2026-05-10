<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = (int) config('boilerplate.files.max_size_kb', 10240);
        $allowedMime = config('boilerplate.files.allowed_mime_types');
        $allowedExt = config('boilerplate.files.allowed_extensions');

        $fileRules = ['required', 'file'];

        if ($maxKb > 0) {
            $fileRules[] = 'max:'.$maxKb;
        }

        if (is_array($allowedMime) && $allowedMime !== []) {
            $fileRules[] = 'mimetypes:'.implode(',', $allowedMime);
        }

        if (is_array($allowedExt) && $allowedExt !== []) {
            $fileRules[] = 'mimes:'.implode(',', $allowedExt);
        }

        return [
            'file' => $fileRules,
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'meta' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file upload is required.',
            'file.file' => 'The uploaded value must be a file.',
            'file.max' => 'The file is larger than the allowed size.',
            'file.mimes' => 'The file extension is not allowed.',
            'file.mimetypes' => 'The file type is not allowed.',
            'visibility.in' => 'Visibility must be either public or private.',
        ];
    }
}
