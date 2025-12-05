<?php

namespace App\Http\Requests\Operations\Roles;

use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'sometimes|string|max:255',
            'capacity'    => 'sometimes|integer|min:0|nullable',
            'description' => 'sometimes|string|max:2000|nullable',
            'is_required' => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0|nullable',
        ];
    }
}
