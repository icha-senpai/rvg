<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationRole extends Model
{
    protected $fillable = [
        'operation_id',

        // Naming
        'role_name',          // internal identifier  
        'role_display_name',  // shown in UI

        // Structure
        'capacity',
        'min_required',
        'description',
        'requirements',

        // Optional for sorting in UI
        'sort_order',
        'is_required'
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_required'  => 'boolean',
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function participants()
    {
        return $this->hasMany(OperationParticipant::class, 'operation_role_id');
    }

    /* Helper: how many members are assigned to this role */
    public function filledCount(): int
    {
        return $this->participants()->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null
            && $this->filledCount() >= $this->capacity;
    }
}
