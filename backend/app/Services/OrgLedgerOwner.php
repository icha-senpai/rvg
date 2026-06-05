<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerShipAsset;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class OrgLedgerOwner extends AbstractLedgerOwner
{
    public function __construct(
        protected User $actor,
        protected LedgerCycleService $cycles,
    ) {}

    public function type(): string
    {
        return 'organization';
    }

    public function label(): string
    {
        return 'Horizon Treasury';
    }

    public function userId(): ?int
    {
        return $this->actor->id;
    }

    public function subjectUser(): User
    {
        return $this->actor;
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
        return true;
    }

    public function primaryAccount(): ?LedgerAccount
    {
        return LedgerAccount::query()
            ->where('is_org_owned', true)
            ->whereNull('squadron_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function applyOwnership(array $attributes): array
    {
        return array_merge($attributes, [
            'user_id' => $this->actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
        ]);
    }

    public function scopeOwned(Builder $query): Builder
    {
        return $query
            ->where('is_org_owned', true)
            ->whereNull('squadron_id');
    }

    public function scopeActivityLogs(Builder $query): Builder
    {
        return $query
            ->where('is_org_owned', true)
            ->whereNull('squadron_id');
    }

    public function resolveAccount(?int $accountId): LedgerAccount
    {
        if (! $accountId) {
            return $this->cycles->defaultOrgAccountFor($this->actor);
        }

        $account = LedgerAccount::query()
            ->whereNull('squadron_id')
            ->where('is_org_owned', true)
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'ledger_account_id' => 'That ledger account is not available for the org ledger.',
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
            ->whereNull('squadron_id')
            ->where('is_org_owned', true)
            ->find($shipAssetId);

        if (! $asset) {
            throw ValidationException::withMessages([
                'ship_asset_id' => 'That ship asset does not belong to the org ledger.',
            ]);
        }

        return $asset->id;
    }

    protected function ownsRecord(Model $record): bool
    {
        return (bool) $record->getAttribute('is_org_owned')
            && blank($record->getAttribute('squadron_id'));
    }
}
