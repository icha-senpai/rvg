<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerShipAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wipe_cycle_id' => ['nullable', 'integer', 'exists:wipe_cycles,id'],
            'vehicle_uex_id' => ['required', 'integer'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'serial_or_label' => ['nullable', 'string', 'max:255'],
            'purchase_price' => ['nullable', 'integer', 'gte:0'],
            'currency' => ['nullable', 'string', 'max:16'],
            'acquisition_source' => ['nullable', 'string', 'max:120'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
            'acquired_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
