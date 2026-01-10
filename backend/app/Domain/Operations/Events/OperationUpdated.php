<?php

namespace App\Domain\Operations\Events;

use App\Models\Operation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperationUpdated
{
    use Dispatchable, SerializesModels;

    public Operation $operation;

    public function __construct(Operation $operation)
    {
        $this->operation = $operation;
    }
}
