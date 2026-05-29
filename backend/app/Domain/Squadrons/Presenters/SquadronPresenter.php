<?php

namespace App\Domain\Squadrons\Presenters;

use App\Application\Squadrons\Presenters\SquadronPresenter as ApplicationSquadronPresenter;

/**
 * @deprecated Use App\Application\Squadrons\Presenters\SquadronPresenter.
 *
 * Squadron payload shaping now lives in the application layer. This wrapper
 * keeps older imports working during the transition.
 */
class SquadronPresenter extends ApplicationSquadronPresenter
{
}
