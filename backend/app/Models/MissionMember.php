<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissionMember extends Model
{
    protected $fillable = [
        'mission_id',
        'user_id',
        'slot',
        'attendance_status',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
    ];

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
