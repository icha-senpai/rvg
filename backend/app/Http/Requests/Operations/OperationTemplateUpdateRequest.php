<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class OperationTemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',

            'payload' => 'sometimes|array',

            'payload.title' => 'nullable|string|max:255',
            'payload.gameplay_type' => 'nullable|string|max:255',
            'payload.description' => 'nullable|string|max:510',
            'payload.notes' => 'nullable|string|max:5000',
            'payload.extended_description' => 'nullable|string|max:5000',

            'payload.visibility' => 'nullable|in:open,squadron,private',
            'payload.squadron_name' => 'nullable|string|max:255',
            'payload.operation_type' => 'nullable|in:operation,squadron_training,wing_training,roleplay,meeting,event',
            'payload.branch' => 'nullable|in:industries,defence,frontiers,lifelines',
            'payload.operation_strictness' => 'nullable|in:casual,normal,strict,roleplay',

            'payload.start_location' => 'nullable|string|max:2000',
            'payload.operation_location' => 'nullable|string|max:2000',

            'payload.slots' => 'nullable|array',
            'payload.slots.*' => 'required|string|max:255|regex:/\S/',
            'payload.roles' => 'nullable|array',
            'payload.roles.*.id' => 'nullable|integer',
            'payload.roles.*.role_name' => 'nullable|string|max:255',
            'payload.roles.*.role_display_name' => 'required|string|max:255|regex:/\S/',
            'payload.roles.*.capacity' => 'nullable|integer|min:0',
        ];
    }
}
