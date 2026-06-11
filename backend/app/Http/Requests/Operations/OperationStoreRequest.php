<?php

namespace App\Http\Requests\Operations;

use App\Domain\Operations\Enums\OperationStatus;
use Illuminate\Foundation\Http\FormRequest;

class OperationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller/policy handles actual permission
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

        if (($data['visibility'] ?? null) === 'squadron_only') {
            $data['visibility'] = 'squadron';
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string|max:510',
            'squadron_name'        => 'nullable|string|max:255',
            'starts_at'            => 'required|date',
            'ends_at'              => 'nullable|date|after:starts_at',

            'operation_type'       => 'required|in:operation,squadron_training,wing_training,roleplay,meeting,event',
            'branch'               => 'nullable|in:industries,defence,frontiers,lifelines',
            'gameplay_type'        => 'nullable|string|max:255',

            'start_location'       => 'nullable|string|max:2000',
            'operation_location'   => 'nullable|string|max:2000',

            'visibility'           => 'nullable|in:open,squadron,private',
            'difficulty'           => 'nullable|in:low,medium,high',
            'operation_strictness' => 'nullable|in:casual,normal,strict,roleplay',

            'icon'                 => 'nullable|string|max:20',
            'image_url'            => 'nullable|url|max:2048',
            'rsvp_deadline'        => 'nullable|date|before:starts_at',
            'notes'                => 'nullable|string|max:5000',
            'extended_description'  => 'nullable|string|max:5000',

            'status'               => 'nullable|in:' . implode(',', OperationStatus::creatableValues()),

            'media_id'             => 'nullable|integer|exists:media,id',

            'slots'                => 'nullable|array',
            'slots.*'              => 'required|string|max:255|regex:/\S/',
            'roles'                => 'nullable|array',
            'roles.*.id'           => 'nullable|integer|exists:operation_roles,id',
            'roles.*.role_name'    => 'nullable|string|max:255',
            'roles.*.role_display_name' => 'required|string|max:255|regex:/\S/',
            'roles.*.capacity'     => 'nullable|integer|min:0',
        ];
    }
}
