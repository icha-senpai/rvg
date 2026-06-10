<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerTradeRequest extends FormRequest
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
            'commodity_uex_id' => ['nullable', 'integer'],
            'buy_terminal_uex_id' => ['nullable', 'integer'],
            'sell_terminal_uex_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'gt:0'],
            'unit_type' => ['nullable', 'string', 'max:32'],
            'buy_price_per_unit' => ['required', 'integer', 'gte:0'],
            'sell_price_per_unit' => ['required', 'integer', 'gte:0'],
            'ship_asset_id' => ['nullable', 'integer', 'exists:ledger_ship_assets,id'],
            'cargo_capacity_used' => ['nullable', 'integer', 'gte:0'],
            'trade_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
