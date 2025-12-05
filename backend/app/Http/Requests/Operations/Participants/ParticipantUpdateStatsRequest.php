<?php

namespace App\Http\Requests\Operations\Participants;

use Illuminate\Foundation\Http\FormRequest;

class ParticipantUpdateStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stats' => 'required|array',
        ];
    }
}
