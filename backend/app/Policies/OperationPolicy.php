<?php

namespace App\Policies;

use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;

class OperationPolicy
{
    private function directorOverride(User $user): bool
    {
        return $user->hasRole('director') || $user->hasRole('tech_director');
    }

    /**
     * Core helper: can this user generally host operations?
     * Uses your existing host tier permissions.
     */
    private function canHostAnyOperation(User $user): bool
    {
        return $user->hasPermission('operation.create')
            || $user->hasPermission('operation.host.small')
            || $user->hasPermission('operation.host.medium')
            || $user->hasPermission('operation.host.large')
            || $user->hasPermission('operation.host.org');
    }

    /* VIEW LIST */
    public function viewAny(User $user): bool
    {
        // Any authenticated member can see ops list
        return true;
    }

    /* VIEW SINGLE */
    public function view(User $user, Operation $operation): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        // Open ops visible to all
        if ($operation->visibility === 'open') {
            return true;
        }

        // Squadron-limited: must match squadron if present
        if ($operation->squadron_id && $user->squadronMemberships()
                ->active()
                ->where('squadron_id', $operation->squadron_id)
                ->exists()) {
            return true;
        }

        // For now: allow general view
        return true;
    }

    /**
     * CREATE
     *
     * We support an optional Squadron parameter so you can do:
     *   $this->authorize('create', [Operation::class, $squadron]);
     */
    public function create(User $user, ?Squadron $squadron = null): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        // Global RBAC: host permissions
        if ($this->canHostAnyOperation($user)) {
            return true;
        }

        // If a squadron is passed, allow its leader to create ops for it
        if ($squadron && $user->isSquadronLeader($squadron)) {
            return true;
        }

        // 🔥 NEW LOGIC: Lieutenants can create ops for THEIR squadron ONLY
        if ($squadron && $user->isSquadronLieutenant($squadron)) {
            return true;
        }

        return false;
    }

    /* UPDATE / DELETE */
    public function update(User $user, Operation $operation): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        // Creator can always update their own operation
        if ($operation->created_by === $user->id) {
            return true;
        }

        // Squadron leader for this squadron can update
        if ($operation->squadron_id) {
            $squadron = Squadron::find($operation->squadron_id);

            if ($squadron && $user->isSquadronLeader($squadron)) {
                return true;
            }
        }

        // Global manage permissions
        return $user->hasPermission('operation.manage')
            || $user->hasPermission('operation.members.manage');
    }

    public function delete(User $user, Operation $operation): bool
    {
        return $this->update($user, $operation);
    }

    /* MANAGE MEMBERS / STATS */
    public function manageMembers(User $user, Operation $operation): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        if ($operation->created_by === $user->id) {
            return true;
        }

        if ($operation->squadron_id) {
            $squadron = Squadron::find($operation->squadron_id);

            if ($squadron && $user->isSquadronLeader($squadron)) {
                return true;
            }
        }

        return $user->hasPermission('operation.members.manage')
            || $user->hasPermission('operation.manage');
    }

    public function adjustStats(User $user, Operation $operation): bool
    {
        return $this->manageMembers($user, $operation);
    }

    /* MANAGE (status, visibility etc) */
    public function manage(User $user, Operation $operation): bool
    {
        return $this->update($user, $operation);
    }
}
