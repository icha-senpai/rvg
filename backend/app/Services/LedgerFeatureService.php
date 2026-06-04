<?php

namespace App\Services;

use App\Domain\AccessControl\AccessService;
use App\Models\User;

class LedgerFeatureService
{
    public function __construct(
        protected AccessService $access,
    ) {}

    public function globallyEnabled(): bool
    {
        return (bool) config('services.ledger.enabled', false);
    }

    public function previewUserIds(): array
    {
        $raw = config('services.ledger.preview_user_ids', []);

        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        return collect($raw)
            ->map(fn ($value) => (int) trim((string) $value))
            ->filter(fn ($value) => $value > 0)
            ->values()
            ->all();
    }

    public function isPreviewUser(User $user): bool
    {
        return in_array($user->id, $this->previewUserIds(), true);
    }

    public function canAccess(User $user): bool
    {
        if ($this->access->isDirectorLike($user) || $this->isPreviewUser($user)) {
            return true;
        }

        if (! $this->globallyEnabled()) {
            return false;
        }

        return $this->access->any($user, [
            'ledger.view-own',
            'ledger.view-any',
        ]);
    }

    public function canEditOwn(User $user): bool
    {
        if ($this->access->isDirectorLike($user) || $this->isPreviewUser($user)) {
            return true;
        }

        if (! $this->globallyEnabled()) {
            return false;
        }

        return $this->access->any($user, [
            'ledger.edit-own',
            'ledger.view-any',
        ]);
    }

    public function canDeleteOwn(User $user): bool
    {
        if ($this->access->isDirectorLike($user) || $this->isPreviewUser($user)) {
            return true;
        }

        if (! $this->globallyEnabled()) {
            return false;
        }

        return $this->access->any($user, [
            'ledger.delete-own',
            'ledger.edit-own',
            'ledger.view-any',
        ]);
    }
}
