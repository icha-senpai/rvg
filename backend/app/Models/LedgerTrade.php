<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerTrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'is_org_owned',
        'ledger_account_id',
        'wipe_cycle_id',
        'commodity_uex_id',
        'buy_terminal_uex_id',
        'sell_terminal_uex_id',
        'quantity',
        'unit_type',
        'buy_price_per_unit',
        'sell_price_per_unit',
        'total_cost',
        'total_revenue',
        'profit',
        'profit_per_unit',
        'ship_asset_id',
        'cargo_capacity_used',
        'trade_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_org_owned' => 'boolean',
            'quantity' => 'decimal:4',
            'buy_price_per_unit' => 'decimal:2',
            'sell_price_per_unit' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'profit' => 'decimal:2',
            'profit_per_unit' => 'decimal:2',
            'cargo_capacity_used' => 'decimal:4',
            'trade_date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function squadron(): BelongsTo
    {
        return $this->belongsTo(Squadron::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function wipeCycle(): BelongsTo
    {
        return $this->belongsTo(WipeCycle::class);
    }

    public function shipAsset(): BelongsTo
    {
        return $this->belongsTo(LedgerShipAsset::class, 'ship_asset_id');
    }
}
