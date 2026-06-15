<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class OperationParticipant extends Model
{
    public const RUNTIME_STATUS_SIGNED_UP = 'signed_up';
    public const RUNTIME_STATUS_SIGNED_OFF_BEFORE_START = 'signed_off_before_start';
    public const RUNTIME_STATUS_NO_SHOW = 'no_show';
    public const RUNTIME_STATUS_EXCUSED = 'excused';
    public const RUNTIME_STATUS_OPERATION_FINISHED = 'operation_finished';
    public const RUNTIME_STATUS_TECHNICAL_ISSUE = 'technical_issue';

    public const RUNTIME_SOURCE_SIGNED_UP = 'signed_up';
    public const RUNTIME_SOURCE_WALK_IN = 'walk_in';
    public const RUNTIME_SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'operation_id',
        'user_id',
        'operation_role_id',
        'operation_discord_channel_id',
        'slot',
        'attendance_status',
        'runtime_status',
        'runtime_source',
        'signed_off_at',
        'synced_in_at',
        'starting_auec',
        'ending_auec',
        'runtime_notes',
        'notes',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
        'signed_off_at' => 'datetime',
        'synced_in_at' => 'datetime',
        'starting_auec' => 'integer',
        'ending_auec' => 'integer',
    ];

    /* Relationships */
    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(OperationRole::class, 'operation_role_id');
    }

    public function discordChannel()
    {
        return $this->belongsTo(OperationDiscordChannel::class, 'operation_discord_channel_id');
    }

    /* Helpers */
    public function isLeader()
    {
        return $this->slot === 'leader'
            || ($this->role && $this->role->role_name === 'leader');
    }

    public function markAttended() { $this->update(['attendance_status' => 'attended']); }
    public function markMissed()   { $this->update(['attendance_status' => 'missed']); }

    public function isSignedOffBeforeStart(): bool
    {
        return $this->runtime_status === self::RUNTIME_STATUS_SIGNED_OFF_BEFORE_START;
    }

    /* Scopes */
    public function scopeWithRole($q, string $roleName)
    {
        return $q->whereHas('role', fn($r) => $r->where('role_name', $roleName));
    }

    public function scopeLeaders($q)
    {
        return $q->where('slot', 'leader')
                 ->orWhereHas('role', fn($r) => $r->where('role_name', 'leader'));
    }
}
