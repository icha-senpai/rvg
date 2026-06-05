<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerShipAsset;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PersonalLedgerOwner extends AbstractLedgerOwner
{
    public function __construct(
        protected User $owner,
        protected LedgerCycleService $cycles,
    ) {}

    public function type(): string
    {
        return 'personal';
    }

    public function label(): string
    {
        return "{$this->displayName($this->owner)}'s Assets & Funds";
    }

    public function userId(): ?int
    {
        return $this->owner->id;
    }

    public function subjectUser(): User
    {
        return $this->owner;
    }

    public function squadronId(): ?int
    {
        return null;
    }

    public function squadron(): ?Squadron
    {
        return null;
    }

    public function isOrgOwned(): bool
    {
        return false;
    }

    public function primaryAccount(): ?LedgerAccount
    {
        return $this->cycles->defaultAccountFor($this->owner);
    }

    public function applyOwnership(array $attributes): array
    {
        return array_merge($attributes, [
            'user_id' => $this->owner->id,
            'squadron_id' => null,
            'is_org_owned' => false,
        ]);
    }

    public function scopeOwned(Builder $query): Builder
    {
        return $query
            ->where('user_id', $this->owner->id)
            ->whereNull('squadron_id')
            ->where('is_org_owned', false);
    }

    public function scopeActivityLogs(Builder $query): Builder
    {
        return $query
            ->where('subject_user_id', $this->owner->id)
            ->whereNull('squadron_id')
            ->where('is_org_owned', false);
    }

    public function resolveAccount(?int $accountId): LedgerAccount
    {
        if (! $accountId) {
            return $this->cycles->defaultAccountFor($this->owner);
        }

        $account = LedgerAccount::query()
            ->where('user_id', $this->owner->id)
            ->whereNull('squadron_id')
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'ledger_account_id' => 'That ledger account is not available for this member.',
            ]);
        }

        return $account;
    }

    public function resolveShipAssetId(?int $shipAssetId): ?int
    {
        if (! $shipAssetId) {
            return null;
        }

        $asset = LedgerShipAsset::query()
            ->where('user_id', $this->owner->id)
            ->whereNull('squadron_id')
            ->where('is_org_owned', false)
            ->find($shipAssetId);

        if (! $asset) {
            throw ValidationException::withMessages([
                'ship_asset_id' => 'That ship asset does not belong to this member.',
            ]);
        }

        return $asset->id;
    }

    protected function ownsRecord(Model $record): bool
    {
        return (int) $record->getAttribute('user_id') === (int) $this->owner->id
            && blank($record->getAttribute('squadron_id'))
            && ! (bool) $record->getAttribute('is_org_owned');
    }
}
