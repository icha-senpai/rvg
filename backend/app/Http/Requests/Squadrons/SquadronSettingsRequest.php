<?php

namespace App\Http\Requests\Squadrons;

use Illuminate\Foundation\Http\FormRequest;

class SquadronSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSquadronLeader($this->route('squadron'));
    }

    public function rules(): array
    {
        return [
            'motto'           => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'primary_color'   => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'recruiting'      => ['boolean'],
        ];
    }
}
