<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationSyncRun extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'operation_id',
        'synced_by_user_id',
        'synced_at',
        'source_channel_ids',
        'present_user_ids',
        'no_show_user_ids',
        'walk_in_user_ids',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'source_channel_ids' => 'array',
        'present_user_ids' => 'array',
        'no_show_user_ids' => 'array',
        'walk_in_user_ids' => 'array',
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function syncedBy()
    {
        return $this->belongsTo(User::class, 'synced_by_user_id');
    }
}
