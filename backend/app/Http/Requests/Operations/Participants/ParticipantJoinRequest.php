<?php

namespace App\Http\Requests\Operations\Participants;

use Illuminate\Foundation\Http\FormRequest;

class ParticipantJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operation_role_id' => 'nullable|exists:operation_roles,id',
            'slot'              => 'nullable|string|max:255',
            'notes'             => 'nullable|string|max:500',
        ];
    }
}
