<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'is_org_owned',
        'name',
        'type',
        'currency',
        'is_default',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_org_owned' => 'boolean',
            'is_default' => 'boolean',
            'is_archived' => 'boolean',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(LedgerTrade::class);
    }
}
