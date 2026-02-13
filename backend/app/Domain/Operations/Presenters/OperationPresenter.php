<?php

namespace App\Domain\Operations\Presenters;

use App\Models\Operation;
use App\Models\User;

class OperationPresenter
{
    protected Operation $operation;
    protected ?User $viewer;

    public function __construct(Operation $operation, ?User $viewer = null)
    {
        $this->operation = $operation;
        $this->operation->loadMissing([
            'squadron',
            'squadron.leader',
            'creator',
            'creator.roles',
        ]);

        $this->viewer = $viewer;
    }

    public static function make(Operation $operation, ?User $viewer = null): self
    {
        return new self($operation, $viewer);
    }

    // ------------------------------------------------------
    // 🔹 SUMMARY (for index lists)
    // ------------------------------------------------------
    public function summary(): array
    {
        return [
            'id'          => $this->operation->id,
            'title'       => $this->operation->title,
            'description' => $this->truncate($this->operation->description, 140),
            'squadron_name' => $this->operation->squadron_name,
            'starts_at'   => $this->operation->starts_at?->toIso8601String(),
            'ends_at'     => $this->operation->ends_at?->toIso8601String(),
            'visibility'  => $this->operation->visibility,
            'difficulty'  => $this->operation->difficulty,
            'operation_type' => $this->operation->operation_type,
            'operation_strictness' => $this->operation->operation_strictness,
            'branch'      => $this->operation->branch,
            'status'      => $this->operation->status,
            'completion_outcome' => $this->operation->completion_outcome,
            'created_by'  => $this->operation->created_by,

            'creator' => $this->operation->creator
                ? [
                    'id' => $this->operation->creator->id,
                    'rsi_handle' => $this->operation->creator->rsi_handle,
                    'discord_avatar' => $this->operation->creator->discord_avatar,
                    'rank' => $this->operation->creator->rank,
                    'rank_level' => $this->operation->creator->rank_level,
                    'rank_name' => $this->operation->creator->rank_name,
                    'roles' => $this->operation->creator->roles->map(fn ($role) => [
                        'slug' => $role->slug,
                        'name' => $role->name,
                    ])->values(),
                ]
                : null,

            'squadron' => [
                'id'   => $this->operation->squadron?->id,
                'name' => $this->operation->squadron?->name,
                'leader' => $this->operation->squadron?->leader
                    ? $this->operation->squadron->leader->only(['id', 'rsi_handle'])
                    : null,
            ],
        ];
    }

    // ------------------------------------------------------
    // 🔹 FULL DETAIL (for MissionShow.vue)
    // ------------------------------------------------------
    public function full(): array
    {
        $this->operation->loadMissing([
            'participants.user',
            'roles.participants.user',
            'images',
            'creator.roles',
        ]);

        // Get the primary operation image (most recent)
        $primaryImage = $this->operation->images->first();

        return [
            'id'             => $this->operation->id,
            'title'          => $this->operation->title,
            'description'    => $this->operation->description,
            'squadron_name'  => $this->operation->squadron_name,
            'starts_at'      => $this->operation->starts_at?->toIso8601String(),
            'ends_at'        => $this->operation->ends_at?->toIso8601String(),
            'visibility'     => $this->operation->visibility,
            'operation_type' => $this->operation->operation_type,
            'branch'         => $this->operation->branch,
            'gameplay_type'  => $this->operation->gameplay_type,
            'difficulty'     => $this->operation->difficulty,
            'operation_strictness' => $this->operation->operation_strictness,
            'icon'           => $this->operation->icon,
            'image_url'      => $this->operation->image_url,
            'start_location' => $this->operation->start_location,
            'operation_location' => $this->operation->operation_location,
            'rsvp_deadline'  => $this->operation->rsvp_deadline?->toIso8601String(),
            'notes'          => $this->operation->notes,
            'extended_description' => $this->operation->extended_description,
            'status'         => $this->operation->status,
            'completion_outcome' => $this->operation->completion_outcome,
            'cancellation_reason' => $this->operation->cancellation_reason,
            'slots'          => $this->operation->slots,

            'media_image'    => $primaryImage ? [
                'id'            => $primaryImage->id,
                'url'           => $primaryImage->url,
                'thumbnail_url' => $primaryImage->thumbnail_url,
                'medium_url'    => $primaryImage->medium_url,
                'alt_text'      => $primaryImage->alt_text,
                'original_filename' => $primaryImage->original_filename,
            ] : null,

            'creator' => [
                'id'   => $this->operation->creator?->id,
                'rsi_handle' => $this->operation->creator?->rsi_handle,
                'discord_avatar' => $this->operation->creator?->discord_avatar,
                'rank' => $this->operation->creator?->rank,
                'rank_level' => $this->operation->creator?->rank_level,
                'rank_name' => $this->operation->creator?->rank_name,
                'roles' => $this->operation->creator
                    ? $this->operation->creator->roles->map(fn ($role) => [
                        'slug' => $role->slug,
                        'name' => $role->name,
                    ])->values()
                    : [],
            ],

            'squadron' => [
                'id'   => $this->operation->squadron?->id,
                'name' => $this->operation->squadron?->name,
                'rsi_handle' => $this->operation->squadron?->rsi_handle,
            ],

            'participants' => $this->operation->participants->map(function ($p) {
                return [
                    'id'     => $p->id,
                    'slot'   => $p->slot,
                    'status' => $p->attendance_status,
                    'notes'  => $p->notes,
                    'role' => $p->role?->only([
                        'id',
                        'role_name',
                        'role_display_name',
                        'capacity',
                    ]),
                    'user' => [
                        'id'           => $p->user->id,
                        'rsi_handle' => $p->user->rsi_handle,
                    ],
                ];
            })->values(),
            
            'roles' => $this->operation->roles->map(function ($role) {
                return [
                    'id'                => $role->id,
                    'role_name'         => $role->role_name,
                    'role_display_name' => $role->role_display_name,
                    'capacity'          => $role->capacity,
                    'min_required'      => $role->min_required,
                    'description'       => $role->description,
                    'requirements'      => $role->requirements,
                ];
            })->values(),
        ];
    }

    // ------------------------------------------------------
    // 🔹 FORM SHAPE (for MissionEditor.vue)
    // ------------------------------------------------------
    public function form(): array
    {
        $this->operation->loadMissing('images');
        $primaryImage = $this->operation->images->first();

        return [
            'id'             => $this->operation->id,
            'title'          => $this->operation->title,
            'description'    => $this->operation->description,
            'squadron_name'  => $this->operation->squadron_name,
            'starts_at'      => optional($this->operation->starts_at)->toIso8601String(),
            'ends_at'        => optional($this->operation->ends_at)->toIso8601String(),
            'visibility'     => $this->operation->visibility,
            'operation_type' => $this->operation->operation_type,
            'branch'         => $this->operation->branch,
            'gameplay_type'  => $this->operation->gameplay_type,
            'difficulty'     => $this->operation->difficulty,
            'operation_strictness' => $this->operation->operation_strictness,
            'icon'           => $this->operation->icon,
            'image_url'      => $this->operation->image_url,
            'start_location' => $this->operation->start_location,
            'operation_location' => $this->operation->operation_location,
            'rsvp_deadline'  => optional($this->operation->rsvp_deadline)->toIso8601String(),
            'notes'          => $this->operation->notes,
            'extended_description' => $this->operation->extended_description,
            'status'         => $this->operation->status,
            'completion_outcome' => $this->operation->completion_outcome,
            'slots'          => $this->operation->slots,

            'media_image'    => $primaryImage ? [
                'id'            => $primaryImage->id,
                'url'           => $primaryImage->url,
                'thumbnail_url' => $primaryImage->thumbnail_url,
                'medium_url'    => $primaryImage->medium_url,
                'alt_text'      => $primaryImage->alt_text,
                'original_filename' => $primaryImage->original_filename,
            ] : null,
        ];
    }

    // ------------------------------------------------------
    // 🔹 Helper
    // ------------------------------------------------------
    protected function truncate(?string $text, int $limit): ?string
    {
        if (!$text) return null;
        return strlen($text) > $limit ? substr($text, 0, $limit) . '…' : $text;
    }
}
