<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationParticipant extends Model
{
    protected $fillable = [
        'operation_id',
        'user_id',

        // Either a structured role relation or a loose slot name
        'operation_role_id',
        'slot',              // e.g. "pilot", "gunner", "logistics"

        'attendance_status', // signed_up / attended / missed etc.
        'notes',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
    ];

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

    public function markAttended()
    {
        $this->update(['attendance_status' => 'attended']);
    }

    public function markMissed()
    {
        $this->update(['attendance_status' => 'missed']);
    }

    public function isLeader()
    {
        return $this->slot === 'leader' || $this->role?->role_name === 'leader';
    }
}
