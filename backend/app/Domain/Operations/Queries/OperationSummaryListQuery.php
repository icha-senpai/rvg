<?php

namespace App\Domain\Operations\Queries;

use App\Application\Operations\Queries\OperationSummaryListQuery as ApplicationOperationSummaryListQuery;

/**
 * @deprecated Use App\Application\Operations\Queries\OperationSummaryListQuery.
 *
 * Query assembly is now treated as an application concern. This wrapper keeps
 * older imports working during the transition.
 */
class OperationSummaryListQuery extends ApplicationOperationSummaryListQuery
{
}
