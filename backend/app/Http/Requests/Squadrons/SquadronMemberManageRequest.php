<?php

namespace App\Http\Requests\Squadrons;

use Illuminate\Foundation\Http\FormRequest;

class SquadronMemberManageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSquadronLeader($this->route('squadron'));
    }

    public function rules(): array
    {
        return [
            'id'                => ['required', 'exists:squadron_members,id'],
            'role'              => ['nullable', 'string', 'in:leader,lieutenant,null'],
            'membership_status' => ['required', 'string', 'in:active,pending,banned'],
        ];
    }
}
