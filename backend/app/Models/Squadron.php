<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Squadron extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function members()
    {
        return $this->hasMany(SquadronMember::class);
    }
}
