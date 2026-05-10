<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Coerce truthy/falsy strings ("true", "false", "1", "0", "on", "off")
     * into real booleans so the boolean validation rule accepts them. Query
     * strings always arrive as strings, and Laravel's boolean rule otherwise
     * rejects "true" / "false".
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('claimed')) {
            $coerced = filter_var(
                $this->input('claimed'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($coerced !== null) {
                $this->merge(['claimed' => $coerced]);
            }
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'claimed' => ['nullable', 'boolean'],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in([
                'created_at', '-created_at',
                'size', '-size',
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visibility.in' => 'Visibility must be either public or private.',
            'sort.in' => 'Sort must be one of: created_at, -created_at, size, -size.',
        ];
    }
}
