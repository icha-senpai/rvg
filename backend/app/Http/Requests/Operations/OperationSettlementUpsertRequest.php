<?php

namespace App\Http\Requests\Operations;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationSettlementUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'money_rows' => ['nullable', 'array'],
            'money_rows.*.row_key' => ['nullable', 'string', 'max:64'],
            'money_rows.*.recipient_type' => ['nullable', Rule::in(['member', 'squadron', 'organization'])],
            'money_rows.*.recipient_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('global_status', User::STATUS_ACTIVE)
                        ->whereNotNull('rsi_verified_at')),
            ],
            'money_rows.*.recipient_squadron_id' => ['nullable', 'integer', Rule::exists('squadrons', 'id')],
            'money_rows.*.amount' => ['nullable', 'integer', 'gte:0'],
            'money_rows.*.notes' => ['nullable', 'string', 'max:2000'],

            'loot_rows' => ['nullable', 'array'],
            'loot_rows.*.row_key' => ['nullable', 'string', 'max:64'],
            'loot_rows.*.source_type' => ['nullable', Rule::in(['item', 'component', 'commodity'])],
            'loot_rows.*.uex_reference_id' => ['nullable', 'integer'],
            'loot_rows.*.recipient_type' => ['nullable', Rule::in(['member', 'squadron', 'organization'])],
            'loot_rows.*.recipient_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('global_status', User::STATUS_ACTIVE)
                        ->whereNotNull('rsi_verified_at')),
            ],
            'loot_rows.*.recipient_squadron_id' => ['nullable', 'integer', Rule::exists('squadrons', 'id')],
            'loot_rows.*.quantity' => ['nullable', 'integer', 'gt:0'],
            'loot_rows.*.unit_label' => ['nullable', 'string', 'max:32'],
            'loot_rows.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
