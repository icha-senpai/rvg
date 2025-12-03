<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:active,inactive,loa'],
            'loa_until' => ['nullable', 'date'],

            'preferred_times' => ['nullable', 'array'],
            'preferred_times.*' => ['string', 'max:255'],

            'focus' => ['nullable', 'array'],
            'focus.*' => ['string', 'max:255'],

            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:255'],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
