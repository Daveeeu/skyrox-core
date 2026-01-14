<?php

namespace App\Modules\HytaleAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'state' => ['required', 'string', 'min:10'],
            'error' => ['nullable', 'string'],
            'error_description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Authorization code is required.',
            'code.string' => 'Authorization code must be a string.',
            'state.required' => 'State parameter is required for security.',
            'state.string' => 'State parameter must be a string.',
            'state.min' => 'Invalid state parameter.',
        ];
    }

    /**
     * Handle authorization errors from OAuth provider
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('error')) {
            throw new \Exception(sprintf(
                'OAuth authorization failed: %s - %s',
                $this->input('error'),
                $this->input('error_description', 'Unknown error')
            ));
        }
    }
}
