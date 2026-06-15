<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_id',
        'money_rows',
        'loot_rows',
        'prep_money_rows',
        'prep_money_rows_updated_by_user_id',
        'prep_money_rows_updated_at',
        'finalized_by_user_id',
        'finalized_at',
        'reopened_by_user_id',
        'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'money_rows' => 'array',
            'loot_rows' => 'array',
            'prep_money_rows' => 'array',
            'prep_money_rows_updated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function prepUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prep_money_rows_updated_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }
}
