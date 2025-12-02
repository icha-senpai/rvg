<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationRole extends Model
{
    protected $fillable = [
        'operation_id',
        'role_name',         // internal identifier, e.g. "wing_lead"
        'role_display_name', // human name, e.g. "Wing Lead"
        'capacity',          // max members in this role
        'min_required',      // min needed for "green" readiness
        'description',
        'requirements',      // json (ship type, certs, etc)
    ];

    protected $casts = [
        'requirements' => 'array',
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function participants()
    {
        return $this->hasMany(OperationParticipant::class, 'operation_role_id');
    }
}
