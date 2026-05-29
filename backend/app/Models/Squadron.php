<?php

namespace App\Models;

use App\Domain\Squadrons\Enums\SquadronStatus;
use Illuminate\Database\Eloquent\Model;

class Squadron extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'branch',
        'division',
        'leader_id',
        'motto',
        'description',
        'primary_color',
        'secondary_color',
        'emblem_path',
        'recruiting',
        'recruitment_propaganda',
    ];

    protected $casts = [
        'recruiting' => 'boolean',
    ];

    /* Relationships */
    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members()
    {
        return $this->hasMany(SquadronMember::class);
    }

    public function activeMembers()
    {
        return $this->members()->where('membership_status', 'active');
    }

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }

    /* Media (polymorphic) */
    public function media()
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function emblem()
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')
            ->where('collection', \App\Models\Media::COLLECTION_SQUADRON_EMBLEM)
            ->latest();
    }

    /* Status helpers */
    public function isActive()    { return $this->status === SquadronStatus::Active->value; }
    public function isInactive()  { return $this->status === SquadronStatus::Inactive->value; }
    public function isDisbanded() { return $this->status === SquadronStatus::Disbanded->value; }

    /* Scopes */
    public function scopeActive($q)
    {
        return $q->where('status', SquadronStatus::Active->value);
    }
}
