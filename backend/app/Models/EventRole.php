<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRole extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'description',
        'slots',
        'requirements'
    ];

    protected $casts = [
        'requirements' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function members()
    {
        return $this->hasMany(EventMember::class);
    }
}
