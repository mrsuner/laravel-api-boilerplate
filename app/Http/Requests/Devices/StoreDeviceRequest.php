<?php

namespace App\Http\Requests\Devices;

use App\Enums\DevicePlatform;
use App\Enums\PushProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
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
            'platform' => ['required', Rule::in(DevicePlatform::values())],
            'provider' => ['required', Rule::in(PushProvider::values())],
            'push_token' => ['required', 'string', 'max:512'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform.in' => 'Platform must be one of: '.implode(', ', DevicePlatform::values()).'.',
            'provider.in' => 'Provider must be one of: '.implode(', ', PushProvider::values()).'.',
        ];
    }
}
