<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents one user's membership record within a squadron.
 */
class SquadronMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'membership_status',
        'role',
        'joined_at',
        'left_at',
        'removed_at',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_BANNED  = 'banned';

    public const ROLE_MEMBER     = 'member';
    public const ROLE_LEADER     = 'leader';
    public const ROLE_LIEUTENANT = 'lieutenant';

    /**
     * Return the squadron that owns this membership record.
     */
    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    /**
     * Return the user attached to this membership record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to active membership records.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('membership_status', self::STATUS_ACTIVE);
    }

    /**
     * Check whether this record represents the active squadron leader.
     */
    public function isLeader(): bool
    {
        return $this->role === self::ROLE_LEADER
            && $this->membership_status === self::STATUS_ACTIVE;
    }

    /**
     * Check whether this record represents an active squadron lieutenant.
     */
    public function isLieutenant(): bool
    {
        return $this->role === self::ROLE_LIEUTENANT
            && $this->membership_status === self::STATUS_ACTIVE;
    }

    /**
     * Check whether this record represents an active regular member.
     */
    public function isMember(): bool
    {
        return ($this->role === self::ROLE_MEMBER || $this->role === null)
            && $this->membership_status === self::STATUS_ACTIVE;
    }

    /**
     * Check whether this membership is active.
     */
    public function isActive(): bool
    {
        return $this->membership_status === self::STATUS_ACTIVE;
    }

    /**
     * Check whether this membership is pending approval.
     */
    public function isPending(): bool
    {
        return $this->membership_status === self::STATUS_PENDING;
    }

    /**
     * Check whether this membership has been banned.
     */
    public function isBanned(): bool
    {
        return $this->membership_status === self::STATUS_BANNED;
    }
}
