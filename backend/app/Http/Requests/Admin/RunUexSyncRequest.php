<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RunUexSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['nullable', 'string', 'in:all,locations,trade,vehicles,industry'],
            'resources' => ['nullable', 'array'],
            'resources.*' => ['string', 'max:120'],
        ];
    }
}
