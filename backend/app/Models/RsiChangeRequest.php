<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RsiChangeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'requested_rsi_handle',
        'notes',
        'status',
        'approved_by',
        'resolved_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    
}


