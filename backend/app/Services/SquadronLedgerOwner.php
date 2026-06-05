<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerShipAsset;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SquadronLedgerOwner extends AbstractLedgerOwner
{
    public function __construct(
        protected Squadron $squadron,
        protected User $actor,
        protected LedgerCycleService $cycles,
    ) {}

    public function type(): string
    {
        return 'squadron';
    }

    public function label(): string
    {
        return "{$this->squadron->name} Squadron Assets & Funds";
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
        return $this->squadron->id;
    }

    public function squadron(): ?Squadron
    {
        return $this->squadron;
    }

    public function isOrgOwned(): bool
    {
        return false;
    }

    public function primaryAccount(): ?LedgerAccount
    {
        return LedgerAccount::query()
            ->where('squadron_id', $this->squadron->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function applyOwnership(array $attributes): array
    {
        return array_merge($attributes, [
            'user_id' => $this->actor->id,
            'squadron_id' => $this->squadron->id,
            'is_org_owned' => false,
        ]);
    }

    public function scopeOwned(Builder $query): Builder
    {
        return $query
            ->where('squadron_id', $this->squadron->id)
            ->where('is_org_owned', false);
    }

    public function scopeActivityLogs(Builder $query): Builder
    {
        return $query
            ->where('squadron_id', $this->squadron->id)
            ->where('is_org_owned', false);
    }

    public function resolveAccount(?int $accountId): LedgerAccount
    {
        if (! $accountId) {
            return $this->cycles->defaultSquadronAccountFor($this->squadron, $this->actor);
        }

        $account = LedgerAccount::query()
            ->where('squadron_id', $this->squadron->id)
            ->where('is_org_owned', false)
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'ledger_account_id' => 'That ledger account is not available for this squadron.',
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
            ->where('squadron_id', $this->squadron->id)
            ->where('is_org_owned', false)
            ->find($shipAssetId);

        if (! $asset) {
            throw ValidationException::withMessages([
                'ship_asset_id' => 'That ship asset does not belong to this squadron.',
            ]);
        }

        return $asset->id;
    }

    protected function ownsRecord(Model $record): bool
    {
        return (int) $record->getAttribute('squadron_id') === (int) $this->squadron->id
            && ! (bool) $record->getAttribute('is_org_owned');
    }
}
