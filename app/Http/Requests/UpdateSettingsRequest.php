<?php

namespace App\Http\Requests;

use App\Support\SettingSchema;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Uses SettingSchema to validate each setting key individually.
     * Only keys defined in the schema are allowed — unknown keys
     * are rejected to prevent arbitrary data injection.
     */
    public function rules(): array
    {
        $rules = [
            'settings' => ['required', 'array'],
        ];

        // Build dynamic rules for each known setting key
        foreach (SettingSchema::rules() as $key => $keyRules) {
            $rules["settings.{$key}"] = $keyRules;
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'Settings data is required.',
            'settings.array' => 'Settings must be an object.',
        ];
    }

    /**
     * Configure the validator to filter out unknown setting keys
     * instead of rejecting the entire request.
     *
     * Unknown keys are silently removed — known keys are still validated
     * against their rules. This prevents 422 errors when the frontend
     * sends keys that haven't been added to the schema yet.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = $this->input('settings', []);

            if (! is_array($settings)) {
                return;
            }

            $allowedKeys = SettingSchema::allowedKeys();
            $filtered = [];

            foreach ($settings as $key => $value) {
                if (in_array($key, $allowedKeys, true)) {
                    $filtered[$key] = $value;
                }
            }

            $this->merge(['settings' => $filtered]);

        });
    }
}
