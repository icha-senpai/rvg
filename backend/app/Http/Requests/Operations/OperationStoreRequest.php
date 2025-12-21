<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class OperationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller/policy handles actual permission
    }

    public function rules(): array
    {
        return [
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'starts_at'            => 'required|date',
            'ends_at'              => 'nullable|date|after:starts_at',

            'operation_kind'       => 'required|in:event,mission',
            'type'                 => 'nullable|string|max:255',

            'visibility'           => 'nullable|in:open,squadron,private',
            'difficulty'           => 'nullable|in:low,medium,high',
            'operation_strictness' => 'nullable|in:casual,normal,strict,roleplay',

            'icon'                 => 'nullable|string|max:20',
            'image_url'            => 'nullable|url|max:2048',
            'rsvp_deadline'        => 'nullable|date|before:starts_at',
            'notes'                => 'nullable|string|max:2000',

            'status'               => 'nullable|in:draft,published',

            'slots'                => 'nullable|array',
            'slots.*'              => 'required|string|max:255|regex:/\S/',
        ];
    }
}
