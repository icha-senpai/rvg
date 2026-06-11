<?php

namespace App\Domain\Squadrons;

use App\Domain\AccessControl\RoleHierarchy;
use App\Domain\States\SquadronState;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Handles admin-only squadron workflows.
 *
 * These flows create and update squadron records, validate proposed leaders,
 * and keep the leader assignment synchronized with the squadron_members table.
 */
class SquadronAdminService
{
    public function __construct(
        protected SquadronSettingsService $settings,
    ) {}

    /**
     * Return the users who are eligible to lead a squadron based on org rank.
     */
    public function eligibleLeaders()
    {
        $eligibleLeaderRoleSlugs = ['commander', 'wing_commander', 'admiral', 'grand_admiral', 'director', 'tech_director'];

        return User::whereHas('roles', function ($query) use ($eligibleLeaderRoleSlugs) {
                $query->whereIn('slug', $eligibleLeaderRoleSlugs);
            })
            ->select('id', 'discord_name', 'rsi_handle', 'rank', 'rank_level')
            ->orderByDesc('rank_level')
            ->orderBy('discord_name')
            ->get();
    }

    /**
     * Confirm that the chosen leader exists and is at least commander rank.
     */
    public function assertEligibleLeader(?int $leaderId): void
    {
        if (! $leaderId) {
            return;
        }

        $leader = User::with('roles:id,slug')->find($leaderId);

        if (! $leader || ! RoleHierarchy::userAtLeast($leader, 'commander')) {
            throw ValidationException::withMessages([
                'leader_id' => 'Selected leader does not have sufficient rank.',
            ]);
        }
    }

    /**
     * Create a new squadron from the admin dashboard payload.
     *
     * Branch/division combinations are validated first, then the leader sync step
     * ensures the optional leader also has a matching active membership record.
     */
    public function createForAdmin(array $data): Squadron
    {
        $this->assertValidBranchDivision($data['branch'] ?? null, $data['division'] ?? null);

        $squadron = Squadron::create(array_merge([
            'status' => SquadronState::ACTIVE,
            'motto' => null,
            'recruiting' => true,
        ], $this->extractAdminAttributes($data)));

        return $this->syncAdminLeaderAssignment(
            $squadron,
            isset($data['leader_id']) ? (int) $data['leader_id'] : null
        );
    }

    /**
     * Update an existing squadron from the admin dashboard.
     */
    public function updateForAdmin(Squadron $squadron, array $data): Squadron
    {
        $this->assertValidBranchDivision($data['branch'] ?? null, $data['division'] ?? null);

        $this->settings->update($squadron, $this->extractAdminAttributes($data));

        return $this->syncAdminLeaderAssignment(
            $squadron->fresh(),
            isset($data['leader_id']) ? (int) $data['leader_id'] : null
        );
    }

    /**
     * Assign a specific user as the current leader for the squadron.
     *
     * The old leader role is demoted first so the membership table never ends up
     * with two active leaders for the same squadron.
     */
    public function assignLeader(Squadron $squadron, User $user): Squadron
    {
        SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LEADER)
            ->update(['role' => SquadronMember::ROLE_MEMBER]);

        // Ensure the chosen leader exists in the squadron membership table and is
        // marked active with the leader role.
        SquadronMember::updateOrCreate(
            [
                'user_id' => $user->id,
                'squadron_id' => $squadron->id,
            ],
            [
                'membership_status' => SquadronMember::STATUS_ACTIVE,
                'role' => SquadronMember::ROLE_LEADER,
                'joined_at' => now(),
            ]
        );

        $squadron->update(['leader_id' => $user->id]);

        return $squadron->fresh();
    }

    /**
     * Repair legacy mismatches between the squadron leader column and the
     * underlying roster rows used everywhere else in the app.
     *
     * Assumption: when `leader_id` disagrees with the roster, the squadron's
     * stored leader assignment is the intended source of truth for repair.
     */
    public function repairRosterConsistency(Squadron $squadron): array
    {
        $squadron = $squadron->fresh();
        $actions = [];

        $activeLeaderRows = SquadronMember::query()
            ->where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LEADER)
            ->get();

        if (! $squadron->leader_id) {
            if ($activeLeaderRows->count() === 1) {
                $inferredLeaderId = (int) $activeLeaderRows->first()->user_id;
                $squadron->update(['leader_id' => $inferredLeaderId]);
                $actions[] = 'Set the squadron leader from the existing leader roster row.';
            } elseif ($activeLeaderRows->count() > 1) {
                $chosenLeaderRow = $activeLeaderRows
                    ->sortByDesc(fn (SquadronMember $member) => $member->joined_at?->getTimestamp() ?? 0)
                    ->first();

                $squadron->update(['leader_id' => $chosenLeaderRow?->user_id]);

                SquadronMember::query()
                    ->where('squadron_id', $squadron->id)
                    ->where('role', SquadronMember::ROLE_LEADER)
                    ->where('id', '!=', $chosenLeaderRow?->id)
                    ->update(['role' => SquadronMember::ROLE_MEMBER]);

                $actions[] = 'Chose one leader roster row and demoted the stale duplicates.';
            }

            return [
                'squadron' => $squadron->fresh(),
                'actions' => $actions,
                'message' => $actions === []
                    ? 'No roster repair was needed.'
                    : implode(' ', $actions),
            ];
        }

        $leaderId = (int) $squadron->leader_id;
        $leaderMemberships = SquadronMember::query()
            ->where('user_id', $leaderId)
            ->orderByRaw('CASE WHEN squadron_id = ? THEN 0 ELSE 1 END', [$squadron->id])
            ->latest('joined_at')
            ->latest('id')
            ->get();

        $primaryMembership = $leaderMemberships->first();

        if (! $primaryMembership) {
            $primaryMembership = SquadronMember::query()->create([
                'user_id' => $leaderId,
                'squadron_id' => $squadron->id,
                'membership_status' => SquadronMember::STATUS_ACTIVE,
                'role' => SquadronMember::ROLE_LEADER,
                'joined_at' => now(),
            ]);

            $actions[] = 'Created the missing leader roster row.';
        } else {
            $primaryUpdates = [];

            if ((int) $primaryMembership->squadron_id !== (int) $squadron->id) {
                $primaryUpdates['squadron_id'] = $squadron->id;
                $actions[] = 'Moved the leader roster row into this squadron.';
            }

            if ($primaryMembership->membership_status !== SquadronMember::STATUS_ACTIVE) {
                $primaryUpdates['membership_status'] = SquadronMember::STATUS_ACTIVE;
            }

            if ($primaryMembership->role !== SquadronMember::ROLE_LEADER) {
                $primaryUpdates['role'] = SquadronMember::ROLE_LEADER;
            }

            if (! $primaryMembership->joined_at) {
                $primaryUpdates['joined_at'] = now();
            }

            if ($primaryMembership->left_at !== null) {
                $primaryUpdates['left_at'] = null;
            }

            if ($primaryMembership->removed_at !== null) {
                $primaryUpdates['removed_at'] = null;
            }

            if ($primaryUpdates !== []) {
                $primaryMembership->update($primaryUpdates);

                if (! in_array('Moved the leader roster row into this squadron.', $actions, true)) {
                    $actions[] = 'Normalized the leader roster row to an active leader membership.';
                }
            }
        }

        $duplicateMembershipCount = SquadronMember::query()
            ->where('user_id', $leaderId)
            ->where('id', '!=', $primaryMembership->id)
            ->delete();

        if ($duplicateMembershipCount > 0) {
            $actions[] = 'Removed duplicate roster rows for the assigned leader.';
        }

        $demotedLeaderCount = SquadronMember::query()
            ->where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LEADER)
            ->where('id', '!=', $primaryMembership->id)
            ->update(['role' => SquadronMember::ROLE_MEMBER]);

        if ($demotedLeaderCount > 0) {
            $actions[] = 'Demoted stale leader-designated roster rows in this squadron.';
        }

        return [
            'squadron' => $squadron->fresh(),
            'actions' => $actions,
            'message' => $actions === []
                ? 'No roster repair was needed.'
                : implode(' ', $actions),
        ];
    }

    /**
     * Summarize whether the squadron leader assignment and roster disagree.
     */
    public function rosterConsistencySummary(Squadron $squadron): array
    {
        $squadron = $squadron->fresh();

        if (! $squadron->leader_id) {
            $leaderRowCount = SquadronMember::query()
                ->where('squadron_id', $squadron->id)
                ->where('role', SquadronMember::ROLE_LEADER)
                ->count();

            if ($leaderRowCount > 0) {
                return [
                    'status' => 'repair_needed',
                    'message' => 'This squadron has a leader roster row but no assigned leader record.',
                ];
            }

            return [
                'status' => 'ok',
                'message' => null,
            ];
        }

        $leaderMembership = SquadronMember::query()
            ->where('squadron_id', $squadron->id)
            ->where('user_id', $squadron->leader_id)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE)
            ->first();

        if (! $leaderMembership) {
            return [
                'status' => 'repair_needed',
                'message' => 'The assigned leader is missing from this squadron roster.',
            ];
        }

        if ($leaderMembership->role !== SquadronMember::ROLE_LEADER) {
            return [
                'status' => 'repair_needed',
                'message' => 'The assigned leader is in the roster but is not marked as the active squadron leader.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => null,
        ];
    }

    /**
     * Pull only the admin-editable squadron fields from the request payload.
     */
    protected function extractAdminAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'branch' => $data['branch'] ?? null,
            'division' => $data['division'] ?? null,
        ];
    }

    /**
     * Keep the squadron's leader_id column and leader membership row in sync.
     *
     * When the leader changes, stale leader-designated membership rows are removed
     * before the new leader is created or updated.
     */
    protected function syncAdminLeaderAssignment(Squadron $squadron, ?int $leaderId): Squadron
    {
        $previousLeaderId = $squadron->leader_id;

        $squadron->update([
            'leader_id' => $leaderId,
        ]);

        if ($previousLeaderId !== $leaderId) {
            // Remove the old leader-designated rows so only the newly selected
            // leader remains marked as leader inside the squadron membership table.
            SquadronMember::where('squadron_id', $squadron->id)
                ->where('role', SquadronMember::ROLE_LEADER)
                ->delete();

            if ($previousLeaderId) {
                SquadronMember::where('squadron_id', $squadron->id)
                    ->where('user_id', $previousLeaderId)
                    ->delete();
            }
        }

        if ($leaderId) {
            SquadronMember::updateOrCreate(
                [
                    'user_id' => $leaderId,
                    'squadron_id' => $squadron->id,
                ],
                [
                    'membership_status' => SquadronMember::STATUS_ACTIVE,
                    'role' => SquadronMember::ROLE_LEADER,
                    'joined_at' => now(),
                ]
            );
        }

        return $squadron->fresh();
    }

    /**
     * Validate that a division belongs to the selected branch.
     *
     * Admin forms may submit one without the other, so this helper centralizes
     * the pairing rule instead of repeating it in controllers.
     */
    protected function assertValidBranchDivision(?string $branch, ?string $division): void
    {
        if (! $branch && ! $division) {
            return;
        }

        if (! $branch || ! $division) {
            throw ValidationException::withMessages([
                'division' => 'Selected division is not valid for the chosen branch.',
            ]);
        }

        $map = [
            'defence' => ['marines', 'navy', 'airforce'],
            'industries' => ['procurement', 'logistics', 'construction'],
            'frontiers' => ['exploration', 'science', 'development'],
            'lifeline' => ['triage', 'recovery', 'medical'],
        ];

        if (! array_key_exists($branch, $map) || ! in_array($division, $map[$branch], true)) {
            throw ValidationException::withMessages([
                'division' => 'Selected division is not valid for the chosen branch.',
            ]);
        }
    }
}
