<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SquadronStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by the controller’s authorize() method
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:squadrons,name',
            'slug' => 'required|string|max:255|unique:squadrons,slug',
        ];
    }
}
