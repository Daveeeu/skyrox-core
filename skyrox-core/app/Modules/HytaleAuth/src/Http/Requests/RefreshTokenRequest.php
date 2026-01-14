<?php

namespace App\Modules\HytaleAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => [
                'required',
                'string',
                'regex:/^hyr_[a-zA-Z0-9]{64}$/', // Hytale refresh token format
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'refresh_token.required' => 'Refresh token is required.',
            'refresh_token.string' => 'Refresh token must be a string.',
            'refresh_token.regex' => 'Invalid refresh token format.',
        ];
    }
}
