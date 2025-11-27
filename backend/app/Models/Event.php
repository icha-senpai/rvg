<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'squadron_id',
        'created_by',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'visibility',
        'status',
        'icon',
        'image_url',
        'difficulty',
        'operation_strictness',
        'rsvp_deadline',
        'notes',
        'status',
        'cancellation_reason',
    ];

    protected $casts = [
        'starts_time' => 'datetime',
        'ends_time' => 'datetime',
        'rsvp_deadline' => 'datetime',
        'status' => 'string',
    ];

    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(EventMember::class);
    }

    public function roles()
    {
        return $this->hasMany(EventRole::class);
    }
    public function transitionTo(string $newStatus, ?string $reason = null): bool
    {
        $validTransitions = [
            'draft' => ['published', 'canceled'],
            'published' => ['in_progress', 'canceled'],
            'in_progress' => ['completed', 'canceled'],
        ];

    if (!in_array($newStatus, $validTransitions[$this->status] ?? [])) {
        throw new \Exception("Invalid transition from {$this->status} to {$newStatus}");
    }

    $this->status = $newStatus;

    if ($newStatus === 'canceled' && $reason) {
        $this->cancellation_reason = $reason;
    }

    return $this->save();
    }


}
