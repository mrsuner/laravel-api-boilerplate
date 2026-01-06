<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
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
        $otpLength = config('boilerplate.auth.otp_length', 6);

        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string', "size:{$otpLength}"],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.size' => 'The OTP must be exactly :size digits.',
        ];
    }
}
