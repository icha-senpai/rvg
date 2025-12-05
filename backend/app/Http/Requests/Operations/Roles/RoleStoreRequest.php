<?php

namespace App\Http\Requests\Operations\Roles;

use Illuminate\Foundation\Http\FormRequest;

class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'capacity'    => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
            'is_required' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ];
    }
}
