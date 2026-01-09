<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'squadron_id',
        'created_by',

        'title',
        'description',

        'starts_at',
        'ends_at',

        // Classification
        'visibility',
        'operation_kind',
        'branch',
        'type',

        // Style
        'difficulty',
        'operation_strictness',
        'icon',
        'image_url',

        'start_location',
        'operation_location',

        // Logistics
        'rsvp_deadline',
        'notes',
        'status',
        'cancellation_reason',

        // Mission structure
        'slots',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'rsvp_deadline'  => 'datetime',
        'slots'          => 'array',
        'status'         => 'string',
    ];

    /* ---------------------------------
     | Relationships
     --------------------------------- */
    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants()
    {
        return $this->hasMany(OperationParticipant::class);
    }

    public function roles()
    {
        return $this->hasMany(OperationRole::class);
    }

    /* ---------------------------------
     | Status Helpers
     --------------------------------- */
    public function isDraft()         { return $this->status === 'draft'; }
    public function isPublished()     { return $this->status === 'published'; }
    public function isInProgress()    { return $this->status === 'in_progress'; }
    public function isCompleted()     { return $this->status === 'completed'; }
    public function isCanceled()      { return $this->status === 'canceled'; }

    /* ---------------------------------
     | Scopes
     --------------------------------- */
    public function scopeUpcoming($q)
    {
        return $q->where('starts_at', '>', now());
    }

    public function scopePast($q)
    {
        return $q->where('ends_at', '<', now());
    }

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['draft', 'published', 'in_progress']);
    }

    /* ---------------------------------
     | Transition System
     --------------------------------- */
    public function transitionTo(string $newStatus, ?string $reason = null): bool
    {
        $valid = [
            'draft'       => ['published', 'canceled'],
            'published'   => ['in_progress', 'canceled'],
            'in_progress' => ['completed',  'canceled'],
        ];

        if (!in_array($newStatus, $valid[$this->status] ?? [])) {
            throw new \Exception("Invalid transition from {$this->status} to {$newStatus}");
        }

        $this->status = $newStatus;

        if ($newStatus === 'canceled') {
            $this->cancellation_reason = $reason;
        }

        return $this->save();
    }
    public function scopeVisibleToUser($query, User $user)
    {
        return $query->whereIn('status', ['published', 'in_progress']);
    }
}