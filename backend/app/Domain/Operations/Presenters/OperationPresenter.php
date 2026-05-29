<?php

namespace App\Domain\Operations\Presenters;

use App\Application\Operations\Presenters\OperationPresenter as ApplicationOperationPresenter;

/**
 * @deprecated Use App\Application\Operations\Presenters\OperationPresenter.
 *
 * This compatibility wrapper keeps older imports working while the operation
 * payload shaping lives in the application layer.
 */
class OperationPresenter extends ApplicationOperationPresenter
{
}
