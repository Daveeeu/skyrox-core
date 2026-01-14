<?php

namespace App\Modules\PlayerPermissions\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class PlayerLogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hytale_uuid' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'hytale_uuid.required' => 'A Hytale UUID kötelező.',
            'hytale_uuid.string' => 'A Hytale UUID szöveg formátumban kell lennie.',
        ];
    }
}
