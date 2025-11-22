<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SquadronMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'membership_status',
        'joined_at',
        'left_at',
        'removed_at',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_BANNED  = 'banned';

    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
