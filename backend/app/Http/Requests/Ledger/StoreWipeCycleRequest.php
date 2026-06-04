<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWipeCycleRequest extends FormRequest
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
            'wipe_type' => ['required', Rule::in(['none', 'partial', 'full', 'economy', 'inventory', 'ships', 'reputation', 'unknown'])],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
