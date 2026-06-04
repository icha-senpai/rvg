<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerShipAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'is_org_owned',
        'wipe_cycle_id',
        'vehicle_uex_id',
        'custom_name',
        'serial_or_label',
        'purchase_price',
        'currency',
        'acquisition_source',
        'current_location',
        'status',
        'acquired_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_org_owned' => 'boolean',
            'purchase_price' => 'decimal:2',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'related_ship_asset_id');
    }
}
