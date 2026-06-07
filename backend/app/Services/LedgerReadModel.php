<?php

namespace App\Services;

use App\Models\Squadron;
use App\Models\User;

class LedgerReadModel
{
    public function __construct(
        protected LedgerPageDataBuilder $pages,
        protected LedgerAdminDataBuilder $admin,
    ) {}

    public function buildPageData(User $user, string $wipeFilter = 'current'): array
    {
        return $this->pages->buildPersonal($user, $wipeFilter);
    }

    public function buildSquadronPageData(Squadron $squadron, string $wipeFilter = 'current', ?User $viewer = null): array
    {
        return $this->pages->buildSquadron($squadron, $wipeFilter, $viewer);
    }

    public function buildOrganizationPageData(User $actor, string $wipeFilter = 'current'): array
    {
        return $this->pages->buildOrganization($actor, $wipeFilter);
    }

    public function buildAdminData(): array
    {
        return $this->admin->build();
    }
}
