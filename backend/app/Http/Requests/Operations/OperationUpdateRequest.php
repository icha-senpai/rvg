<?php

namespace App\Http\Requests\Operations;

use App\Domain\Operations\Enums\OperationStatus;
use Illuminate\Foundation\Http\FormRequest;

class OperationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (! array_key_exists('operation_type', $data) && array_key_exists('operation_kind', $data)) {
            $data['operation_type'] = $data['operation_kind'];
        }

        if (! array_key_exists('gameplay_type', $data) && array_key_exists('type', $data)) {
            $data['gameplay_type'] = $data['type'];
        }

        if (! array_key_exists('extended_description', $data) && array_key_exists('notes', $data)) {
            $data['extended_description'] = $data['notes'];
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'title'                => 'sometimes|string|max:255',
            'description'          => 'sometimes|string|nullable|max:510',
            'squadron_name'        => 'sometimes|nullable|string|max:255',
            'starts_at'            => 'sometimes|date',
            'ends_at'              => 'sometimes|nullable|date|after:starts_at',

            'operation_type'       => 'sometimes|in:operation,squadron_training,wing_training,roleplay,meeting,event',
            'branch'               => 'sometimes|nullable|in:industries,defence,frontiers,lifelines',
            'gameplay_type'        => 'sometimes|string|max:255|nullable',

            'start_location'       => 'sometimes|nullable|string|max:2000',
            'operation_location'   => 'sometimes|nullable|string|max:2000',

            'visibility'           => 'sometimes|nullable|in:open,squadron,private',
            'difficulty'           => 'sometimes|nullable|in:low,medium,high',
            'operation_strictness' => 'sometimes|nullable|in:casual,normal,strict,roleplay',

            'icon'                 => 'sometimes|string|max:20|nullable',
            'image_url'            => 'sometimes|url|max:2048|nullable',
            'rsvp_deadline'        => 'sometimes|date|before:starts_at|nullable',
            'notes'                => 'sometimes|string|max:5000|nullable',
            'extended_description'  => 'sometimes|string|max:5000|nullable',

            'slots'                => 'sometimes|array|nullable',
            'slots.*'              => 'required|string|max:255|regex:/\S/',
            'roles'                => 'sometimes|array|nullable',
            'roles.*.id'           => 'nullable|integer|exists:operation_roles,id',
            'roles.*.role_name'    => 'nullable|string|max:255',
            'roles.*.role_display_name' => 'required|string|max:255|regex:/\S/',
            'roles.*.capacity'     => 'nullable|integer|min:0',

            'status'               => 'sometimes|in:' . implode(',', OperationStatus::values()),

            'media_id'             => 'nullable|integer|exists:media,id',
        ];
    }
}
