<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WipeCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'star_citizen_version',
        'wipe_type',
        'started_at',
        'ended_at',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }
}
