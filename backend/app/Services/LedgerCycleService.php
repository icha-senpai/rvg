<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
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

    public function applyWipeFilter($query, array $filter)
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
}
