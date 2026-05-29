<?php

namespace App\Domain\Operations\Services;

use App\Application\Operations\OperationShowDataService as ApplicationOperationShowDataService;

/**
 * @deprecated Use App\Application\Operations\OperationShowDataService.
 *
 * Operation show/edit payload assembly is now treated as an application concern.
 * This wrapper keeps older imports working during the transition.
 */
class OperationShowDataService extends ApplicationOperationShowDataService
{
}
