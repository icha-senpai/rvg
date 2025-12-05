<?php

namespace App\Domain\Operations\Presenters;

use Illuminate\Support\Collection;

class RoleListPresenter
{
    protected Collection $roles;

    public function __construct(Collection $roles)
    {
        $this->roles = $roles;
    }

    public static function make(Collection $roles): self
    {
        return new static($roles);
    }

    public function toArray(): array
    {
        return $this->roles
            ->sortBy('sort_order')
            ->map(fn ($role) => RolePresenter::make($role)->toArray())
            ->values()
            ->toArray();
    }
}
