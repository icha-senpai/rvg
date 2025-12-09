<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class OperationParticipant extends Model
{
    

    protected $fillable = [
        'operation_id',
        'user_id',
        'operation_role_id',
        'slot',
        'attendance_status',
        'notes',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
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

    /* Helpers */
    public function isLeader()
    {
        return $this->slot === 'leader'
            || ($this->role && $this->role->role_name === 'leader');
    }

    public function markAttended() { $this->update(['attendance_status' => 'attended']); }
    public function markMissed()   { $this->update(['attendance_status' => 'missed']); }

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
