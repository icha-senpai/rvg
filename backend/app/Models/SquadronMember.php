<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SquadronMember extends Model
{
    use HasFactory;

    /* --------------------------------------
     | FILLABLE FIELDS
     -------------------------------------- */
    protected $fillable = [
        'user_id',
        'squadron_id',
        'membership_status',
        'role',
        'joined_at',
        'left_at',
        'removed_at',
    ];

    /* --------------------------------------
     | STATUS CONSTANTS
     -------------------------------------- */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_BANNED  = 'banned';

    /* --------------------------------------
     | ROLE CONSTANTS
     -------------------------------------- */
    public const ROLE_MEMBER     = 'member';
    public const ROLE_LEADER     = 'leader';
    public const ROLE_LIEUTENANT = 'lieutenant';

    /* --------------------------------------
     | RELATIONSHIPS
     -------------------------------------- */

    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('membership_status', self::STATUS_ACTIVE);
    }

    /* --------------------------------------
     | ROLE HELPERS
     -------------------------------------- */

    public function isLeader(): bool
    {
        return $this->role === self::ROLE_LEADER
            && $this->membership_status === self::STATUS_ACTIVE;
    }

    public function isLieutenant(): bool
    {
        return $this->role === self::ROLE_LIEUTENANT
            && $this->membership_status === self::STATUS_ACTIVE;
    }

    public function isMember(): bool
    {
        return ($this->role === self::ROLE_MEMBER || $this->role === null)
            && $this->membership_status === self::STATUS_ACTIVE;
    }

    /* --------------------------------------
     | STATUS HELPERS
     -------------------------------------- */

    public function isActive(): bool
    {
        return $this->membership_status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->membership_status === self::STATUS_PENDING;
    }

    public function isBanned(): bool
    {
        return $this->membership_status === self::STATUS_BANNED;
    }
}
