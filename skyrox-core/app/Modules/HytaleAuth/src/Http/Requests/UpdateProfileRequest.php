<?php

namespace App\Modules\HytaleAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\w\s\-_.]+$/u' // Only alphanumeric, spaces, hyphens, underscores, dots
            ],
            'avatar_url' => [
                'nullable',
                'url',
                'max:500',
                'regex:/\.(jpg|jpeg|png|gif|webp)$/i' // Only image URLs
            ],
            'locale' => [
                'nullable',
                'string',
                'size:2',
                Rule::in(['en', 'hu', 'de', 'fr', 'es', 'it', 'pt', 'ru', 'ja', 'ko', 'zh'])
            ],
            'timezone' => [
                'nullable',
                'string',
                'timezone'
            ],
            'preferences' => [
                'nullable',
                'array',
                'max:20' // Maximum 20 preference keys
            ],
            'preferences.*' => [
                'nullable',
                'string',
                'max:255'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'display_name.min' => 'Display name must be at least 2 characters.',
            'display_name.max' => 'Display name cannot exceed 50 characters.',
            'display_name.regex' => 'Display name contains invalid characters.',
            'avatar_url.url' => 'Avatar URL must be a valid URL.',
            'avatar_url.regex' => 'Avatar URL must point to an image file.',
            'locale.size' => 'Locale must be a 2-character language code.',
            'locale.in' => 'Unsupported locale.',
            'timezone.timezone' => 'Invalid timezone.',
            'preferences.max' => 'Too many preferences (maximum 20).',
            'preferences.*.max' => 'Preference value too long (maximum 255 characters).',
        ];
    }

    /**
     * Get validated preferences with filtering
     */
    public function getValidatedPreferences(): array
    {
        $preferences = $this->validated('preferences', []);
        
        // Filter out null/empty values
        return array_filter($preferences, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    /**
     * Sanitize display name
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('display_name')) {
            $this->merge([
                'display_name' => trim($this->input('display_name'))
            ]);
        }
    }
}
