<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class RenameWipeCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'star_citizen_version' => ['nullable', 'string', 'max:64'],
            'wipe_type' => ['required', 'string', 'max:64'],
            'started_at' => ['nullable', 'date'],
        ];
    }
}
