<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SquadronUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255|unique:squadrons,name,' . $this->route('squadron')->id,
            'slug' => 'sometimes|string|max:255|unique:squadrons,slug,' . $this->route('squadron')->id,
            'status' => 'sometimes|string|in:active,inactive,disbanded',
        ];
    }
}
