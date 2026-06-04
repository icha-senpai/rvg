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
        'transfer_request_id',
        'reversal_of_transaction_id',
        'ledger_account_id',
        'wipe_cycle_id',
        'type',
        'amount',
        'currency',
        'source_type',
        'transfer_direction',
        'description',
        'transaction_date',
        'related_ship_asset_id',
        'related_operation_id',
        'operation_settlement_id',
        'related_uex_type',
        'related_uex_id',
        'notes',
        'provenance_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_org_owned' => 'boolean',
            'amount' => 'decimal:2',
            'transaction_date' => 'datetime',
            'provenance_locked' => 'boolean',
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

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(LedgerTransferRequest::class, 'transfer_request_id');
    }

    public function reversalOfTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_transaction_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'related_operation_id');
    }

    public function operationSettlement(): BelongsTo
    {
        return $this->belongsTo(OperationSettlement::class, 'operation_settlement_id');
    }
}
