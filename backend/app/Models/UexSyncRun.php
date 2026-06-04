<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UexSyncRun extends Model
{
    protected $fillable = [
        'scope',
        'requested_resources',
        'status',
        'resource_results',
        'total_records',
        'successful_resources',
        'failed_resources',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_resources' => 'array',
            'resource_results' => 'array',
            'total_records' => 'integer',
            'successful_resources' => 'integer',
            'failed_resources' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
