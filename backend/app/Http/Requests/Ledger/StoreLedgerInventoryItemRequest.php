<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wipe_cycle_id' => ['nullable', 'integer', 'exists:wipe_cycles,id'],
            'source_type' => ['required', Rule::in(['item', 'component', 'commodity', 'custom'])],
            'uex_reference_type' => ['nullable', 'string', 'max:64'],
            'uex_reference_id' => ['nullable', 'integer'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_label' => ['nullable', 'string', 'max:32'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'terminal_uex_id' => ['nullable', 'integer'],
            'assigned_ship_asset_id' => ['nullable', 'integer', 'exists:ledger_ship_assets,id'],
            'purchase_price' => ['nullable', 'numeric', 'gte:0'],
            'estimated_value' => ['nullable', 'numeric', 'gte:0'],
            'currency' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'string', 'max:64'],
            'acquired_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
