<?php

namespace App\Http\Requests\Operations;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationAfterActionReportUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'after_action_report' => ['nullable', 'string', 'max:20000'],
            'attendance_user_ids' => ['nullable', 'array'],
            'attendance_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('global_status', User::STATUS_ACTIVE)
                        ->whereNotNull('rsi_verified_at')),
            ],
            'no_show_user_ids' => ['nullable', 'array'],
            'no_show_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('global_status', User::STATUS_ACTIVE)
                        ->whereNotNull('rsi_verified_at')),
            ],
        ];
    }
}
