<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Squadron extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'leader_id', // IMPORTANT: You must add this
        'motto',
        'description',
        'primary_color',
        'secondary_color',
        'emblem_path',
        'recruiting',
    ];

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_INACTIVE  = 'inactive';
    public const STATUS_DISBANDED = 'disbanded';


    /**
     * All membership rows (any status)
     */
    public function members()
    {
        return $this->hasMany(SquadronMember::class);
    }


    /**
     * Only active members
     */
    public function activeMembers()
    {
        return $this->members()->active();
    }


    /**
     * Legacy: return all members who have "leader" as squadron role
     * (supports future multiple-leader systems)
     */
    public function leaders()
    {
        return $this->members()
            ->active()
            ->where('role', SquadronMember::ROLE_LEADER);
    }



}
