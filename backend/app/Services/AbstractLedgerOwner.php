<?php

namespace App\Services;

use App\Models\LedgerInventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

abstract class AbstractLedgerOwner implements LedgerOwner
{
    public function activityContext(): array
    {
        return [
            $this->subjectUser(),
            $this->squadron(),
            $this->isOrgOwned(),
        ];
    }

    public function assertOwns(Model $record, bool $allowLockedInventory = false): void
    {
        if ($this->ownsRecord($record)) {
            $this->ensureRecordIsMutable($record, $allowLockedInventory);

            return;
        }

        $exception = new ModelNotFoundException;
        $exception->setModel($record::class, [$record->getKey()]);

        throw $exception;
    }

    protected function displayName(User $user): string
    {
        return $user->rsi_handle
            ?? $user->discord_name
            ?? $user->name
            ?? "Member {$user->id}";
    }

    abstract protected function ownsRecord(Model $record): bool;

    protected function ensureRecordIsMutable(Model $record, bool $allowLockedInventory = false): void
    {
        $wipeCycle = $record->relationLoaded('wipeCycle')
            ? $record->getRelation('wipeCycle')
            : $record->wipeCycle;

        if ($wipeCycle && ! $wipeCycle->is_current) {
            throw ValidationException::withMessages([
                'wipe_cycle_id' => 'Archived cycles are read only. Switch back to the current cycle to make changes.',
            ]);
        }

        if (
            ! $allowLockedInventory
            && $record instanceof LedgerInventoryItem
            && (bool) $record->provenance_locked
        ) {
            throw ValidationException::withMessages([
                'inventory' => 'Transferred inventory stays locked so its transfer history remains intact. Move it again instead of editing or deleting it.',
            ]);
        }
    }
}
