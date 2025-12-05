<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\Squadron;

class CreateOperation
{
    public function execute(array $data, Squadron $squadron): Operation
    {
        return Operation::create([
            ...$data,
            'squadron_id' => $squadron->id,
            'status'      => 'draft',
        ]);
    }
}
