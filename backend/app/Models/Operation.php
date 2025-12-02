<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    protected $fillable = [
        'squadron_id',
        'created_by',

        'title',
        'description',

        'starts_at',
        'ends_at',

        // Visibility & classification
        'visibility',          // open / squadron / private
        'operation_kind',      // 'event' or 'mission'
        'type',                // e.g. operation, squadron_training, mission_subtype, etc.

        // Flavor & rules
        'difficulty',          // low / medium / high
        'operation_strictness',// casual / normal / strict / roleplay
        'icon',
        'image_url',

        // Logistics
        'rsvp_deadline',
        'notes',
        'status',              // draft / published / in_progress / completed / canceled
        'cancellation_reason',

        // Mission-style slots (optional)
        'slots',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'rsvp_deadline'  => 'datetime',
        'slots'          => 'array',
        'status'         => 'string',
    ];

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

    /**
     * Status transitions (based on Event::transitionTo)
     */
    public function transitionTo(string $newStatus, ?string $reason = null): bool
    {
        $validTransitions = [
            'draft'      => ['published', 'canceled'],
            'published'  => ['in_progress', 'canceled'],
            'in_progress'=> ['completed', 'canceled'],
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
