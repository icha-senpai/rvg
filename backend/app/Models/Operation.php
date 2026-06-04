<?php

namespace App\Models;

use App\Domain\Operations\Enums\OperationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents one scheduled operation together with its lifecycle state,
 * participants, and media.
 */
class Operation extends Model
{
    protected $fillable = [
        'squadron_id',
        'squadron_name',
        'created_by',

        'title',
        'description',
        'extended_description',

        'starts_at',
        'ends_at',

        // Classification
        'visibility',
        'operation_type',
        'branch',
        'gameplay_type',

        // Style
        'difficulty',
        'operation_strictness',
        'icon',
        'image_url',

        'start_location',
        'operation_location',

        // Logistics and lifecycle fields track scheduling and outcomes.
        'rsvp_deadline',
        'notes',
        'status',
        'completion_outcome',
        'after_action_report',
        'after_action_attendance_user_ids',
        'after_action_no_show_user_ids',
        'after_action_report_updated_at',
        'cancellation_reason',

        // Discord announcement tracking keeps the linked bot message addressable.
        'discord_message_id',

        // Mission structure stores the serialized slot definition payload.
        'slots',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'rsvp_deadline'  => 'datetime',
        'slots'          => 'array',
        'status'         => 'string',
        'completion_outcome' => 'string',
        'after_action_attendance_user_ids' => 'array',
        'after_action_no_show_user_ids' => 'array',
        'after_action_report_updated_at' => 'datetime',
    ];

    /**
     * Return the squadron that owns this operation when it is squadron-scoped.
     */
    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }

    /**
     * Return the user who created this operation.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Return the participant rows attached to this operation.
     */
    public function participants()
    {
        return $this->hasMany(OperationParticipant::class);
    }

    /**
     * Return the role/slot definitions attached to this operation.
     */
    public function roles()
    {
        return $this->hasMany(OperationRole::class);
    }

    /**
     * Return the settlement workspace recorded for this completed operation.
     */
    public function settlement()
    {
        return $this->hasOne(OperationSettlement::class);
    }

    /**
     * Return all polymorphic media attached to this operation.
     */
    public function media()
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    /**
     * Return only operation-image media attached to this operation.
     */
    public function images()
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable')
            ->where('collection', \App\Models\Media::COLLECTION_OPERATION_IMAGE);
    }

    /**
     * Check whether the operation is still in draft state.
     */
    public function isDraft(): bool
    {
        return $this->status === OperationStatus::Draft->value;
    }

    /**
     * Check whether the operation has been published.
     */
    public function isPublished(): bool
    {
        return $this->status === OperationStatus::Published->value;
    }

    /**
     * Check whether the operation is currently in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === OperationStatus::InProgress->value;
    }

    /**
     * Check whether the operation has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === OperationStatus::Completed->value;
    }

    /**
     * Check whether the operation has been canceled.
     */
    public function isCanceled(): bool
    {
        return $this->status === OperationStatus::Canceled->value;
    }

    /**
     * Scope the query to operations that start in the future.
     */
    public function scopeUpcoming($q)
    {
        return $q->where('starts_at', '>', now());
    }

    /**
     * Scope the query to operations that have already ended.
     */
    public function scopePast($q)
    {
        return $q->where('ends_at', '<', now());
    }

    /**
     * Scope the query to operations in an active lifecycle state.
     */
    public function scopeActive($q)
    {
        return $q->whereIn('status', [
            OperationStatus::Draft->value,
            OperationStatus::Published->value,
            OperationStatus::InProgress->value,
        ]);
    }

    /**
     * Scope the query to the operation states that are visible to a normal user.
     */
    public function scopeVisibleToUser($query, User $user)
    {
        return $query->whereIn('status', [
            OperationStatus::Published->value,
            OperationStatus::InProgress->value,
        ]);
    }
}
