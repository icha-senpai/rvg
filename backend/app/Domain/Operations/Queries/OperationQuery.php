<?php

namespace App\Domain\Operations\Queries;

use App\Application\Operations\Queries\OperationQuery as ApplicationOperationQuery;

/**
 * @deprecated Use App\Application\Operations\Queries\OperationQuery.
 *
 * Query orchestration now lives in the application layer. This wrapper keeps
 * older imports working during the transition.
 */
class OperationQuery extends ApplicationOperationQuery
{
}
