<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Domain\Operations\Events\OperationUpdated;

class UpdateOperation
{
    public function execute(Operation $operation, array $data): Operation
    {
        $operation->update($data);

        $discordEmbedFields = [
            'title',
            'description',
            'starts_at',
            'start_location',
            'operation_strictness',
            'squadron_name',
            'operation_type',
            'created_by',
        ];

        if ($operation->status === 'published' && $operation->wasChanged($discordEmbedFields)) {
            OperationUpdated::dispatch($operation);
        }

        return $operation->fresh();
    }
}
