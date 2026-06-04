<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'is_org_owned',
        'wipe_cycle_id',
        'related_operation_id',
        'operation_settlement_id',
        'transfer_request_id',
        'transfer_origin_item_id',
        'source_type',
        'uex_reference_type',
        'uex_reference_id',
        'custom_name',
        'category',
        'quantity',
        'unit_label',
        'location_name',
        'terminal_uex_id',
        'assigned_ship_asset_id',
        'purchase_price',
        'estimated_value',
        'currency',
        'status',
        'provenance_locked',
        'acquired_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_org_owned' => 'boolean',
            'quantity' => 'decimal:4',
            'purchase_price' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'provenance_locked' => 'boolean',
            'acquired_at' => 'datetime',
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

    public function wipeCycle(): BelongsTo
    {
        return $this->belongsTo(WipeCycle::class);
    }

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(LedgerTransferRequest::class, 'transfer_request_id');
    }

    public function transferOriginItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transfer_origin_item_id');
    }

    public function assignedShipAsset(): BelongsTo
    {
        return $this->belongsTo(LedgerShipAsset::class, 'assigned_ship_asset_id');
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
