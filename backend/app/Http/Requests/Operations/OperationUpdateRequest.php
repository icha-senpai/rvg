<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class OperationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                => 'sometimes|string|max:255',
            'description'          => 'sometimes|string|nullable',
            'starts_at'            => 'sometimes|date',
            'ends_at'              => 'sometimes|nullable|date|after:starts_at',

            'operation_kind'       => 'sometimes|in:event,mission',
            'type'                 => 'sometimes|string|max:255|nullable',

            'visibility'           => 'sometimes|nullable|in:open,squadron,private',
            'difficulty'           => 'sometimes|nullable|in:low,medium,high',
            'operation_strictness' => 'sometimes|nullable|in:casual,normal,strict,roleplay',

            'icon'                 => 'sometimes|string|max:20|nullable',
            'image_url'            => 'sometimes|url|max:2048|nullable',
            'rsvp_deadline'        => 'sometimes|date|before:starts_at|nullable',
            'notes'                => 'sometimes|string|max:2000|nullable',

            'slots'                => 'sometimes|array|nullable',
            'slots.*'              => 'required|string|max:255|regex:/\S/',

            'status'               => 'sometimes|in:draft,published,in_progress,completed,canceled',
        ];
    }
}
