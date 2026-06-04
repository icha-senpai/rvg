<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'is_org_owned',
        'ledger_account_id',
        'wipe_cycle_id',
        'type',
        'amount',
        'currency',
        'source_type',
        'description',
        'transaction_date',
        'related_ship_asset_id',
        'related_operation_id',
        'related_uex_type',
        'related_uex_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_org_owned' => 'boolean',
            'amount' => 'decimal:2',
            'transaction_date' => 'datetime',
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
        return $this->belongsTo(LedgerShipAsset::class, 'related_ship_asset_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'related_operation_id');
    }
}
