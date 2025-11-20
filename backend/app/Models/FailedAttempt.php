<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedAttempt extends Model
{
    protected $fillable = [
        'action',
        'ip_address',
        'user_id',
        'attempts',
        'locked_until',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
    ];
}
