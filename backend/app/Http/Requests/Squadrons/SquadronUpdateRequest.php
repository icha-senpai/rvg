<?php

namespace App\Http\Requests\Squadrons;

use Illuminate\Foundation\Http\FormRequest;

class SquadronUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('squadron'));
    }

    public function rules(): array
    {
        $id = $this->route('squadron')->id;

        return [
            'name'            => ['sometimes', 'string', 'max:255'],
            'slug'            => ['sometimes', "string", "max:255", "unique:squadrons,slug,{$id}"],
            'status'          => ['sometimes', 'string', 'in:active,inactive,disbanded'],
            'motto'           => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'primary_color'   => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'recruiting'      => ['boolean'],
        ];
    }
}
