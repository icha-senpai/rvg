<?php

namespace App\Http\Requests\Squadrons;

use Illuminate\Foundation\Http\FormRequest;

class SquadronAssignLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('squadron'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
        ];
    }
}
