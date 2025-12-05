<?php

namespace App\Domain\Operations\Presenters;

use Illuminate\Support\Collection;

class ParticipantListPresenter
{
    protected Collection $participants;

    public function __construct(Collection $participants)
    {
        $this->participants = $participants;
    }

    public static function make(Collection $participants): self
    {
        return new static($participants);
    }

    public function toArray(): array
    {
        return $this->participants
            ->map(fn ($p) => ParticipantPresenter::make($p)->toArray())
            ->values()
            ->toArray();
    }
}
