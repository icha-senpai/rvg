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
        'after_action_signed_off_early_user_ids',
        'after_action_excused_user_ids',
        'after_action_report_updated_at',
        'cancellation_reason',

        // Discord announcement tracking keeps the linked bot message addressable.
        'discord_message_id',
        'discord_message_targets',

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
        'after_action_signed_off_early_user_ids' => 'array',
        'after_action_excused_user_ids' => 'array',
        'after_action_report_updated_at' => 'datetime',
        'discord_message_targets' => 'array',
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
     * Return the recorded runtime sync passes for this operation.
     */
    public function syncRuns()
    {
        return $this->hasMany(OperationSyncRun::class)->orderByDesc('synced_at')->orderByDesc('id');
    }

    /**
     * Return the role/slot definitions attached to this operation.
     */
    public function roles()
    {
        return $this->hasMany(OperationRole::class);
    }

    /**
     * Return the Discord voice channels configured for this operation runtime.
     */
    public function discordChannels()
    {
        return $this->hasMany(OperationDiscordChannel::class)->orderBy('sort_order')->orderBy('id');
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
        $activeMemberships = $user->squadronMemberships()
            ->active()
            ->with('squadron:id,name')
            ->get();

        $activeSquadronIds = $activeMemberships
            ->pluck('squadron_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $activeSquadronNames = $activeMemberships
            ->pluck('squadron.name')
            ->filter()
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $query
            ->whereIn('status', [
                OperationStatus::Published->value,
                OperationStatus::InProgress->value,
            ])
            ->where(function ($visibilityQuery) use ($user, $activeSquadronIds, $activeSquadronNames) {
                $visibilityQuery
                    ->whereNull('visibility')
                    ->orWhere('visibility', 'open')
                    ->orWhere(function ($privateQuery) use ($user) {
                        $privateQuery
                            ->where('visibility', 'private')
                            ->where('created_by', $user->id);
                    })
                    ->orWhere(function ($squadronQuery) use ($activeSquadronIds, $activeSquadronNames) {
                        $squadronQuery->where('visibility', 'squadron');

                        if ($activeSquadronIds === [] && $activeSquadronNames === []) {
                            $squadronQuery->whereRaw('1 = 0');

                            return;
                        }

                        $squadronQuery->where(function ($audienceQuery) use ($activeSquadronIds, $activeSquadronNames) {
                            if ($activeSquadronIds !== []) {
                                $audienceQuery->orWhereIn('squadron_id', $activeSquadronIds);
                            }

                            foreach ($activeSquadronNames as $name) {
                                $this->applySquadronNameAudienceMatch($audienceQuery, $name);
                            }
                        });
                    });
            });
    }

    protected function applySquadronNameAudienceMatch($query, string $name): void
    {
        $query
            ->orWhere('squadron_name', $name)
            ->orWhere('squadron_name', 'like', $name . ', %')
            ->orWhere('squadron_name', 'like', '%, ' . $name)
            ->orWhere('squadron_name', 'like', '%, ' . $name . ', %');
    }
}
