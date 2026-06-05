<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface LedgerOwner
{
    public function type(): string;

    public function label(): string;

    public function userId(): ?int;

    public function subjectUser(): User;

    public function squadronId(): ?int;

    public function squadron(): ?Squadron;

    public function isOrgOwned(): bool;

    public function primaryAccount(): ?LedgerAccount;

    public function applyOwnership(array $attributes): array;

    public function scopeOwned(Builder $query): Builder;

    public function scopeActivityLogs(Builder $query): Builder;

    public function resolveAccount(?int $accountId): LedgerAccount;

    public function resolveShipAssetId(?int $shipAssetId): ?int;

    public function activityContext(): array;

    public function assertOwns(Model $record, bool $allowLockedInventory = false): void;
}
