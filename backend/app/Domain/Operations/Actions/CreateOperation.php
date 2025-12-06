<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Support\Facades\Auth;

class CreateOperation
{
    public function execute(array $data, Squadron $squadron): Operation
    {
       

        return Operation::create([
            ...$data,
            'squadron_id' => $squadron->id,
            'created_by'  => Auth::id(),      
            'status'      => 'draft',
        ]);
    }
}
