<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'squadron_id',
        'created_by',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'visibility',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(EventMember::class);
    }
}
