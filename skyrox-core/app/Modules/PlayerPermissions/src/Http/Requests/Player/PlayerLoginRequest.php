<?php

namespace App\Modules\PlayerPermissions\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class PlayerLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hytale_uuid' => ['required', 'string', 'max:255'],
            'player_name' => ['nullable', 'string', 'max:255'],
            'server_name' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
        ];
    }

    public function messages(): array
    {
        return [
            'hytale_uuid.required' => 'A Hytale UUID kötelező.',
            'hytale_uuid.string' => 'A Hytale UUID szöveg formátumban kell lennie.',
            'hytale_uuid.max' => 'A Hytale UUID maximum 255 karakter lehet.',
        ];
    }
}
