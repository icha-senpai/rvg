<?php

namespace App\Domain\Operations\Events;

use App\Models\Operation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperationCanceled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Operation $operation
    ) {}
}
