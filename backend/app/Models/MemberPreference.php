<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MemberPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'loa_until',
        'preferred_times',
        'focus',
        'roles',
        'notes',
    ];

    protected $casts = [
        'loa_until'      => 'date',
        'preferred_times'=> 'array',
        'focus'          => 'array',
        'roles'          => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
