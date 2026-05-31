<?php

namespace App\Application\Operations\Presenters;

use App\Domain\Media\Presenters\MediaPresenter;
use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;

/**
 * Shapes operation models into the payloads used by operation list pages,
 * detail screens, and editor forms.
 */
class OperationPresenter
{
    protected Operation $operation;
    protected ?User $viewer;

    public function __construct(Operation $operation, ?User $viewer = null)
    {
        $this->operation = $operation;
        $this->operation->loadMissing([
            'squadron',
            'squadron.emblem',
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

    public function summary(): array
    {
        return [
            'id' => $this->operation->id,
            'title' => $this->operation->title,
            'description' => $this->truncate($this->operation->description, 140),
            'squadron_name' => $this->operation->squadron_name,
            'starts_at' => $this->operation->starts_at?->toIso8601String(),
            'ends_at' => $this->operation->ends_at?->toIso8601String(),
            'visibility' => $this->operation->visibility,
            'difficulty' => $this->operation->difficulty,
            'operation_type' => $this->operation->operation_type,
            'operation_strictness' => $this->operation->operation_strictness,
            'branch' => $this->operation->branch,
            'status' => $this->operation->status,
            'completion_outcome' => $this->operation->completion_outcome,
            'after_action_report' => $this->operation->after_action_report,
            'after_action_attendance_user_ids' => $this->operation->after_action_attendance_user_ids ?? [],
            'after_action_no_show_user_ids' => $this->operation->after_action_no_show_user_ids ?? [],
            'after_action_report_updated_at' => $this->operation->after_action_report_updated_at?->toIso8601String(),
            'created_by' => $this->operation->created_by,
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
                'id' => $this->operation->squadron?->id,
                'name' => $this->operation->squadron?->name,
                'emblem_url' => $this->operation->squadron?->emblem
                    ? $this->operation->squadron->emblem->display_url
                    : ($this->operation->squadron?->emblem_path ? asset('storage/' . $this->operation->squadron->emblem_path) : null),
                'emblem' => $this->operation->squadron?->emblem
                    ? MediaPresenter::make($this->operation->squadron->emblem)->embedded()
                    : null,
                'leader' => $this->operation->squadron?->leader
                    ? $this->operation->squadron->leader->only(['id', 'rsi_handle'])
                    : null,
            ],
            'selected_squadrons' => $this->selectedSquadronsPayload(),
        ];
    }

    public function full(): array
    {
        $this->operation->loadMissing([
            'participants.user',
            'roles.participants.user',
            'images',
            'creator.roles',
        ]);

        $primaryImage = $this->operation->images->first();

        return [
            'id' => $this->operation->id,
            'title' => $this->operation->title,
            'description' => $this->operation->description,
            'squadron_name' => $this->operation->squadron_name,
            'starts_at' => $this->operation->starts_at?->toIso8601String(),
            'ends_at' => $this->operation->ends_at?->toIso8601String(),
            'visibility' => $this->operation->visibility,
            'operation_type' => $this->operation->operation_type,
            'branch' => $this->operation->branch,
            'gameplay_type' => $this->operation->gameplay_type,
            'difficulty' => $this->operation->difficulty,
            'operation_strictness' => $this->operation->operation_strictness,
            'icon' => $this->operation->icon,
            'image_url' => $this->operation->image_url,
            'start_location' => $this->operation->start_location,
            'operation_location' => $this->operation->operation_location,
            'rsvp_deadline' => $this->operation->rsvp_deadline?->toIso8601String(),
            'notes' => $this->operation->notes,
            'extended_description' => $this->operation->extended_description,
            'status' => $this->operation->status,
            'completion_outcome' => $this->operation->completion_outcome,
            'after_action_report' => $this->operation->after_action_report,
            'after_action_attendance_user_ids' => $this->operation->after_action_attendance_user_ids ?? [],
            'after_action_no_show_user_ids' => $this->operation->after_action_no_show_user_ids ?? [],
            'after_action_report_updated_at' => $this->operation->after_action_report_updated_at?->toIso8601String(),
            'cancellation_reason' => $this->operation->cancellation_reason,
            'slots' => $this->operation->slots,
            'media_image' => $primaryImage ? [
                'id' => $primaryImage->id,
                'url' => $primaryImage->url,
                'thumbnail_url' => $primaryImage->thumbnail_url,
                'medium_url' => $primaryImage->medium_url,
                'alt_text' => $primaryImage->alt_text,
                'original_filename' => $primaryImage->original_filename,
            ] : null,
            'creator' => [
                'id' => $this->operation->creator?->id,
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
                'id' => $this->operation->squadron?->id,
                'name' => $this->operation->squadron?->name,
                'rsi_handle' => $this->operation->squadron?->rsi_handle,
                'emblem_url' => $this->operation->squadron?->emblem
                    ? $this->operation->squadron->emblem->display_url
                    : ($this->operation->squadron?->emblem_path ? asset('storage/' . $this->operation->squadron->emblem_path) : null),
                'emblem' => $this->operation->squadron?->emblem
                    ? MediaPresenter::make($this->operation->squadron->emblem)->embedded()
                    : null,
            ],
            'selected_squadrons' => $this->selectedSquadronsPayload(),
            'participants' => $this->operation->participants->map(function ($participant) {
                return [
                    'id' => $participant->id,
                    'slot' => $participant->slot,
                    'status' => $participant->attendance_status,
                    'notes' => $participant->notes,
                    'role' => $participant->role?->only([
                        'id',
                        'role_name',
                        'role_display_name',
                        'capacity',
                    ]),
                    'user' => [
                        'id' => $participant->user->id,
                        'rsi_handle' => $participant->user->rsi_handle,
                        'discord_name' => $participant->user->discord_name,
                        'discord_avatar' => $participant->user->discord_avatar,
                        'name' => $participant->user->name,
                    ],
                ];
            })->values(),
            'roles' => $this->operation->roles->sortBy('sort_order')->values()->map(function ($role) {
                $filledCount = $role->participants->count();
                $remainingSpots = $role->capacity !== null
                    ? max(0, $role->capacity - $filledCount)
                    : null;

                return [
                    'id' => $role->id,
                    'role_name' => $role->role_name,
                    'role_display_name' => $role->role_display_name,
                    'capacity' => $role->capacity,
                    'filled_count' => $filledCount,
                    'remaining_spots' => $remainingSpots,
                    'is_full' => $role->capacity !== null && $filledCount >= $role->capacity,
                    'min_required' => $role->min_required,
                    'description' => $role->description,
                    'requirements' => $role->requirements,
                ];
            })->values(),
        ];
    }

    public function form(): array
    {
        $this->operation->loadMissing(['images', 'roles']);
        $primaryImage = $this->operation->images->first();
        $formRoles = $this->operation->roles->isNotEmpty()
            ? $this->operation->roles
                ->sortBy('sort_order')
                ->map(fn ($role) => [
                    'id' => $role->id,
                    'role_name' => $role->role_name,
                    'role_display_name' => $role->role_display_name,
                    'capacity' => $role->capacity,
                ])
                ->values()
                ->all()
            : collect($this->operation->slots ?? [])
                ->map(fn ($slot) => [
                    'id' => null,
                    'role_name' => '',
                    'role_display_name' => $slot,
                    'capacity' => null,
                ])
                ->values()
                ->all();

        return [
            'id' => $this->operation->id,
            'title' => $this->operation->title,
            'description' => $this->operation->description,
            'squadron_name' => $this->operation->squadron_name,
            'starts_at' => optional($this->operation->starts_at)->toIso8601String(),
            'ends_at' => optional($this->operation->ends_at)->toIso8601String(),
            'visibility' => $this->operation->visibility,
            'operation_type' => $this->operation->operation_type,
            'branch' => $this->operation->branch,
            'gameplay_type' => $this->operation->gameplay_type,
            'difficulty' => $this->operation->difficulty,
            'operation_strictness' => $this->operation->operation_strictness,
            'icon' => $this->operation->icon,
            'image_url' => $this->operation->image_url,
            'start_location' => $this->operation->start_location,
            'operation_location' => $this->operation->operation_location,
            'rsvp_deadline' => optional($this->operation->rsvp_deadline)->toIso8601String(),
            'notes' => $this->operation->notes,
            'extended_description' => $this->operation->extended_description,
            'status' => $this->operation->status,
            'completion_outcome' => $this->operation->completion_outcome,
            'slots' => $this->operation->slots,
            'roles' => $formRoles,
            'media_image' => $primaryImage ? [
                'id' => $primaryImage->id,
                'url' => $primaryImage->url,
                'thumbnail_url' => $primaryImage->thumbnail_url,
                'medium_url' => $primaryImage->medium_url,
                'alt_text' => $primaryImage->alt_text,
                'original_filename' => $primaryImage->original_filename,
            ] : null,
        ];
    }

    protected function truncate(?string $text, int $limit): ?string
    {
        if (! $text) {
            return null;
        }

        return strlen($text) > $limit ? substr($text, 0, $limit) . '…' : $text;
    }

    protected function selectedSquadronsPayload(): array
    {
        $names = collect(explode(',', (string) ($this->operation->squadron_name ?? '')))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        $squadrons = Squadron::query()
            ->with('emblem')
            ->whereIn('name', $names->all())
            ->get()
            ->keyBy('name');

        return $names
            ->map(function (string $name) use ($squadrons) {
                $squadron = $squadrons->get($name);

                if (! $squadron) {
                    return [
                        'id' => null,
                        'name' => $name,
                        'emblem_url' => null,
                        'emblem' => null,
                    ];
                }

                $emblemUrl = null;

                if ($squadron->relationLoaded('emblem') && $squadron->emblem) {
                    $emblemUrl = $squadron->emblem->display_url;
                } elseif ($squadron->emblem_path) {
                    $emblemUrl = asset('storage/' . $squadron->emblem_path);
                }

                return [
                    'id' => $squadron->id,
                    'name' => $squadron->name,
                    'emblem_url' => $emblemUrl,
                    'emblem' => $squadron->relationLoaded('emblem') && $squadron->emblem
                        ? MediaPresenter::make($squadron->emblem)->embedded()
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
