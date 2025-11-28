<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // User is already behind auth:sanctum, so allow here
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', 'string', 'max:64'],

            'preferred_roles' => ['nullable', 'array'],
            'preferred_roles.*' => ['string', 'max:50'],

            'notification_settings' => ['nullable', 'array'],

            'availability_status' => ['nullable', 'string', 'max:32'],
            'loa_note' => ['nullable', 'string', 'max:1000'],

            'personal_tags' => ['nullable', 'array'],
            'personal_tags.*' => ['string', 'max:50'],
        ];
    }
}
