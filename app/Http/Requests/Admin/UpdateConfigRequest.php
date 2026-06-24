<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only whitelisted, boolean-toggleable config keys are accepted.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'auth.otp_auth_enabled' => ['sometimes', 'boolean'],
            'auth.password_auth_enabled' => ['sometimes', 'boolean'],
            'audit.enabled' => ['sometimes', 'boolean'],
        ];
    }
}
