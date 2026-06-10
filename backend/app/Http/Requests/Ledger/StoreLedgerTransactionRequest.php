<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ledger_account_id' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
            'wipe_cycle_id' => ['nullable', 'integer', 'exists:wipe_cycles,id'],
            'type' => ['required', Rule::in(['income', 'expense', 'adjustment'])],
            'amount' => ['required', 'integer'],
            'currency' => ['nullable', 'string', 'max:16'],
            'source_type' => ['nullable', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:255'],
            'transaction_date' => ['nullable', 'date'],
            'related_ship_asset_id' => ['nullable', 'integer', 'exists:ledger_ship_assets,id'],
            'related_operation_id' => ['nullable', 'integer', 'exists:operations,id'],
            'related_uex_type' => ['nullable', 'string', 'max:64'],
            'related_uex_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
