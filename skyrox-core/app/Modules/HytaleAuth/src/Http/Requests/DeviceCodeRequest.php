<?php

namespace App\Modules\HytaleAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_code' => ['required', 'string'],
            'user_code' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_code.required' => 'Device code is required.',
            'device_code.string' => 'Device code must be a string.',
            'user_code.required' => 'User code is required.',
            'user_code.string' => 'User code must be a string.',
            'user_code.regex' => 'Invalid user code format. Expected format: ABCD-1234',
        ];
    }
}
