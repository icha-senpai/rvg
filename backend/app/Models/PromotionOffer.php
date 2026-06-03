<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionOffer extends Model
{
    use HasFactory;

    public const STATE_PENDING = 'pending';
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_EXPIRED = 'expired';

    protected $fillable = [
        'member_id',
        'promoter_id',
        'cancelled_by_user_id',
        'from_rank',
        'to_rank',
        'discord_branch_role_id',
        'discord_dm_message_id',
        'discord_quarter_message_id',
        'state',
        'expires_at',
        'accepted_at',
        'cancelled_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
