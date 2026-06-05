<?php

namespace App\Services;

use App\Models\LedgerActivityLog;
use App\Models\LedgerAccount;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerCycleService
{
    public function currentWipeCycle(): WipeCycle
    {
        return $this->ensureCurrentWipeCycle();
    }

    public function defaultAccountFor(User $user): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('user_id', $user->id)
            ->whereNull('squadron_id')
            ->where('is_org_owned', false)
            ->where('is_default', true)
            ->first();

        if ($account) {
            return $account;
        }

        $firstAccount = LedgerAccount::query()
            ->where('user_id', $user->id)
            ->whereNull('squadron_id')
            ->where('is_org_owned', false)
            ->orderBy('id')
            ->first();

        if ($firstAccount) {
            $firstAccount->forceFill(['is_default' => true])->save();

            return $firstAccount->fresh();
        }

        return LedgerAccount::query()->create([
            'user_id' => $user->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'name' => 'Personal Ledger',
            'type' => 'personal',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);
    }

    public function defaultSquadronAccountFor(Squadron $squadron, User $actor): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('squadron_id', $squadron->id)
            ->where('is_org_owned', false)
            ->where('is_default', true)
            ->first();

        if ($account) {
            return $account;
        }

        $firstAccount = LedgerAccount::query()
            ->where('squadron_id', $squadron->id)
            ->where('is_org_owned', false)
            ->orderBy('id')
            ->first();

        if ($firstAccount) {
            $firstAccount->forceFill(['is_default' => true])->save();

            return $firstAccount->fresh();
        }

        return LedgerAccount::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
            'name' => "{$squadron->name} Ledger",
            'type' => 'squadron',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);
    }

    public function defaultOrgAccountFor(User $actor): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('is_org_owned', true)
            ->whereNull('squadron_id')
            ->where('is_default', true)
            ->first();

        if ($account) {
            return $account;
        }

        $firstAccount = LedgerAccount::query()
            ->where('is_org_owned', true)
            ->whereNull('squadron_id')
            ->orderBy('id')
            ->first();

        if ($firstAccount) {
            $firstAccount->forceFill(['is_default' => true])->save();

            return $firstAccount->fresh();
        }

        return LedgerAccount::query()->create([
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
            'name' => 'Horizon Treasury',
            'type' => 'organization',
            'currency' => 'aUEC',
            'is_default' => true,
        ]);
    }

    public function resolveWipeFilter(string $wipeFilter, WipeCycle $currentWipe): array
    {
        if ($wipeFilter === 'all') {
            return [
                'key' => 'all',
                'mode' => 'all',
                'wipe' => null,
            ];
        }

        if ($wipeFilter === 'current' || $wipeFilter === '') {
            return [
                'key' => 'current',
                'mode' => 'specific',
                'wipe' => $currentWipe,
            ];
        }

        $wipe = WipeCycle::query()->find((int) $wipeFilter);

        if (! $wipe) {
            return [
                'key' => 'current',
                'mode' => 'specific',
                'wipe' => $currentWipe,
            ];
        }

        return [
            'key' => (string) $wipe->id,
            'mode' => 'specific',
            'wipe' => $wipe,
        ];
    }

    public function applyWipeFilter(Builder|QueryBuilder $query, array $filter): Builder|QueryBuilder
    {
        if ($filter['mode'] !== 'specific' || ! $filter['wipe']) {
            return $query;
        }

        return $query->where('wipe_cycle_id', $filter['wipe']->id);
    }

    public function resolveWipeCycle(?int $wipeCycleId): WipeCycle
    {
        if (! $wipeCycleId) {
            return $this->ensureCurrentWipeCycle();
        }

        $wipeCycle = WipeCycle::query()->find($wipeCycleId);

        if (! $wipeCycle) {
            throw ValidationException::withMessages([
                'wipe_cycle_id' => 'That wipe cycle does not exist.',
            ]);
        }

        return $wipeCycle;
    }

    public function resolveWritableWipeCycle(?int $wipeCycleId): WipeCycle
    {
        $wipeCycle = $this->resolveWipeCycle($wipeCycleId);

        if (! $wipeCycle->is_current) {
            throw ValidationException::withMessages([
                'wipe_cycle_id' => 'Archived cycles are read only. Switch back to the current cycle to make changes.',
            ]);
        }

        return $wipeCycle;
    }

    public function createWipeCycle(User $actor, array $data): WipeCycle
    {
        return DB::transaction(function () use ($actor, $data) {
            WipeCycle::query()
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'ended_at' => now(),
                ]);

            $wipeCycle = WipeCycle::query()->create([
                'name' => $data['name'],
                'star_citizen_version' => $data['star_citizen_version'] ?? null,
                'wipe_type' => $data['wipe_type'] ?? 'unknown',
                'started_at' => $data['started_at'] ?? now(),
                'ended_at' => null,
                'is_current' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logActivity($actor, $wipeCycle, 'wipe_cycle.created', $wipeCycle, [
                'name' => $wipeCycle->name,
                'wipe_type' => $wipeCycle->wipe_type,
            ]);

            return $wipeCycle;
        });
    }

    public function setCurrentWipeCycle(User $actor, WipeCycle $wipeCycle): WipeCycle
    {
        return DB::transaction(function () use ($actor, $wipeCycle) {
            WipeCycle::query()->where('is_current', true)->update(['is_current' => false]);
            $wipeCycle->forceFill([
                'is_current' => true,
                'ended_at' => null,
            ])->save();

            $this->logActivity($actor, $wipeCycle, 'wipe_cycle.current_set', $wipeCycle, [
                'name' => $wipeCycle->name,
            ]);

            return $wipeCycle->fresh();
        });
    }

    public function closeWipeCycle(User $actor, WipeCycle $wipeCycle): WipeCycle
    {
        $wipeCycle->forceFill([
            'is_current' => false,
            'ended_at' => $wipeCycle->ended_at ?? now(),
        ])->save();

        $this->logActivity($actor, $wipeCycle, 'wipe_cycle.closed', $wipeCycle, [
            'name' => $wipeCycle->name,
        ]);

        if (! WipeCycle::query()->where('is_current', true)->exists()) {
            $this->ensureCurrentWipeCycle();
        }

        return $wipeCycle->fresh();
    }

    public function updateCurrentWipeCycle(User $actor, WipeCycle $wipeCycle, array $data): WipeCycle
    {
        if (! $wipeCycle->is_current) {
            throw ValidationException::withMessages([
                'name' => 'Only the current live cycle can be renamed.',
            ]);
        }

        $wipeCycle->forceFill([
            'name' => $data['name'],
            'star_citizen_version' => $data['star_citizen_version'] ?? null,
            'wipe_type' => $data['wipe_type'] ?? $wipeCycle->wipe_type,
            'started_at' => $data['started_at'] ?? $wipeCycle->started_at,
        ])->save();

        $this->logActivity($actor, $wipeCycle, 'wipe_cycle.updated', $wipeCycle, [
            'name' => $wipeCycle->name,
            'wipe_type' => $wipeCycle->wipe_type,
        ]);

        return $wipeCycle->fresh();
    }

    protected function ensureCurrentWipeCycle(): WipeCycle
    {
        $current = WipeCycle::query()
            ->where('is_current', true)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($current) {
            return $current;
        }

        $version = \Illuminate\Support\Facades\DB::table('uex_game_versions')
            ->orderBy('id')
            ->value('live');

        return WipeCycle::query()->create([
            'name' => $version ? "{$version} Live" : 'Current Live',
            'star_citizen_version' => $version,
            'wipe_type' => 'unknown',
            'started_at' => now(),
            'is_current' => true,
        ]);
    }

    protected function logActivity(
        User $actor,
        ?WipeCycle $wipeCycle,
        string $action,
        object $target,
        array $metadata = []
    ): void {
        LedgerActivityLog::query()->create([
            'actor_user_id' => $actor->id,
            'subject_user_id' => null,
            'squadron_id' => null,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipeCycle?->id,
            'action' => $action,
            'target_type' => class_basename($target),
            'target_id' => $target->id ?? null,
            'metadata' => $metadata,
            'created_at' => CarbonImmutable::now(),
        ]);
    }
}
