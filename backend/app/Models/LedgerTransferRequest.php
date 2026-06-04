<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerTransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_kind',
        'status',
        'requested_by_user_id',
        'approval_user_id',
        'reversal_user_id',
        'wipe_cycle_id',
        'source_user_id',
        'source_squadron_id',
        'source_is_org_owned',
        'destination_user_id',
        'destination_squadron_id',
        'destination_is_org_owned',
        'amount',
        'quantity',
        'currency',
        'description',
        'transaction_date',
        'notes',
        'rejection_reason',
        'reversal_notes',
        'source_inventory_item_id',
        'destination_inventory_item_id',
        'outgoing_transaction_id',
        'incoming_transaction_id',
        'reversal_outgoing_transaction_id',
        'reversal_incoming_transaction_id',
        'approved_at',
        'rejected_at',
        'completed_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_is_org_owned' => 'boolean',
            'destination_is_org_owned' => 'boolean',
            'amount' => 'decimal:2',
            'quantity' => 'decimal:4',
            'transaction_date' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_user_id');
    }

    public function reversalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversal_user_id');
    }

    public function wipeCycle(): BelongsTo
    {
        return $this->belongsTo(WipeCycle::class);
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function sourceSquadron(): BelongsTo
    {
        return $this->belongsTo(Squadron::class, 'source_squadron_id');
    }

    public function destinationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_user_id');
    }

    public function destinationSquadron(): BelongsTo
    {
        return $this->belongsTo(Squadron::class, 'destination_squadron_id');
    }

    public function sourceInventoryItem(): BelongsTo
    {
        return $this->belongsTo(LedgerInventoryItem::class, 'source_inventory_item_id');
    }

    public function destinationInventoryItem(): BelongsTo
    {
        return $this->belongsTo(LedgerInventoryItem::class, 'destination_inventory_item_id');
    }

    public function outgoingTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'outgoing_transaction_id');
    }

    public function incomingTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'incoming_transaction_id');
    }

    public function reversalOutgoingTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'reversal_outgoing_transaction_id');
    }

    public function reversalIncomingTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'reversal_incoming_transaction_id');
    }
}
