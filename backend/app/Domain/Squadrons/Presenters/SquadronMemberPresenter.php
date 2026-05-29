<?php

namespace App\Domain\Squadrons\Presenters;

use App\Application\Squadrons\Presenters\SquadronMemberPresenter as ApplicationSquadronMemberPresenter;

/**
 * @deprecated Use App\Application\Squadrons\Presenters\SquadronMemberPresenter.
 *
 * Squadron membership payload shaping now lives in the application layer. This
 * wrapper keeps older imports working during the transition.
 */
class SquadronMemberPresenter extends ApplicationSquadronMemberPresenter
{
}
