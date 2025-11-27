<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventMember extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'attendance_status',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
        return $this->role === 'leader';
    }

    public function role()
    {
        return $this->belongsTo(EventRole::class, 'event_role_id');
    }

}
