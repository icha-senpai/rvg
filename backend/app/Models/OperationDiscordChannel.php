<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationDiscordChannel extends Model
{
    protected $fillable = [
        'operation_id',
        'name',
        'discord_channel_id',
        'sort_order',
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function participants()
    {
        return $this->hasMany(OperationParticipant::class, 'operation_discord_channel_id');
    }
}
