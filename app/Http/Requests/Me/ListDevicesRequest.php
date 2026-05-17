<?php

namespace App\Http\Requests\Me;

use App\Enums\PushProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDevicesRequest extends FormRequest
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
        return [
            'provider' => ['nullable', Rule::in(PushProvider::values())],
            'sort' => ['nullable', 'string', Rule::in([
                'last_used_at', '-last_used_at',
                'created_at', '-created_at',
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
            'provider.in' => 'Provider must be one of: '.implode(', ', PushProvider::values()).'.',
            'sort.in' => 'Sort must be one of: last_used_at, -last_used_at, created_at, -created_at.',
        ];
    }
}
