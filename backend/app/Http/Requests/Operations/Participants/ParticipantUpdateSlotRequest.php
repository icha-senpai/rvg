<?php

namespace App\Http\Requests\Operations\Participants;

use Illuminate\Foundation\Http\FormRequest;

class ParticipantUpdateSlotRequest extends FormRequest
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
        ];
    }
}
