<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SquadronMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'squadron_id',
        'joined_at',
    ];

    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
