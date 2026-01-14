<?php

namespace App\Modules\PlayerPermissions\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class CheckPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hytale_uuid' => ['required', 'string', 'max:255'],
            'permission' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'hytale_uuid.required' => 'A Hytale UUID kötelező.',
            'hytale_uuid.string' => 'A Hytale UUID szöveg formátumban kell lennie.',
            'permission.required' => 'A jogosultság neve kötelező.',
            'permission.string' => 'A jogosultság neve szövegnek kell lennie.',
            'permission.max' => 'A jogosultság neve maximum 255 karakter lehet.',
        ];
    }
}
