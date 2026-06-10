<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerInventoryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:ledger_inventory_items,id'],
            'destination_type' => ['required', Rule::in(['personal', 'squadron', 'organization', 'external'])],
            'destination_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'destination_squadron_id' => ['nullable', 'integer', 'exists:squadrons,id'],
            'quantity' => ['required', 'integer', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
