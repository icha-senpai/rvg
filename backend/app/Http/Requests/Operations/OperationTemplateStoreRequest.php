<?php

namespace App\Http\Requests\Operations;

use App\Models\OperationTemplate;
use Illuminate\Foundation\Http\FormRequest;

class OperationTemplateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'scope' => 'required|in:' . implode(',', [
                OperationTemplate::SCOPE_PERSONAL,
                OperationTemplate::SCOPE_SQUADRON,
                OperationTemplate::SCOPE_GLOBAL,
            ]),
            'squadron_id' => 'nullable|integer|exists:squadrons,id|required_if:scope,' . OperationTemplate::SCOPE_SQUADRON,

            'payload' => 'required|array',

            'payload.title' => 'nullable|string|max:255',
            'payload.type' => 'nullable|string|max:255',
            'payload.description' => 'nullable|string',
            'payload.notes' => 'nullable|string|max:2000',

            'payload.visibility' => 'nullable|in:open,squadron,private',
            'payload.squadron_name' => 'nullable|string|max:255',
            'payload.operation_kind' => 'nullable|in:operation,squadron_training,wing_training,roleplay,meeting,event',
            'payload.branch' => 'nullable|in:industries,defence,frontiers,lifelines',
            'payload.operation_strictness' => 'nullable|in:casual,normal,strict,roleplay',

            'payload.start_location' => 'nullable|string|max:2000',
            'payload.operation_location' => 'nullable|string|max:2000',

            'payload.slots' => 'nullable|array',
            'payload.slots.*' => 'required|string|max:255|regex:/\S/',
        ];
    }
}
