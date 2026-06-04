<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wipe_cycle_id' => ['nullable', 'integer', 'exists:wipe_cycles,id'],
            'destination_type' => ['required', Rule::in(['personal', 'squadron', 'organization'])],
            'destination_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'destination_squadron_id' => ['nullable', 'integer', 'exists:squadrons,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
