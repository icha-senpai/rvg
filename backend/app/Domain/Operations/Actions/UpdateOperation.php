<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\OperationStatus;
use App\Models\Operation;
use App\Domain\Operations\Events\OperationUpdated;
use Illuminate\Support\Facades\Schema;

class UpdateOperation
{
    public function execute(Operation $operation, array $data): Operation
    {
        $operation->update($this->normalizeDatabaseColumns($data));

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

        if ($operation->status === OperationStatus::Published->value && $operation->wasChanged($discordEmbedFields)) {
            OperationUpdated::dispatch($operation);
        }

        return $operation->fresh();
    }

    protected function normalizeDatabaseColumns(array $data): array
    {
        if (! Schema::hasColumn('operations', 'operation_type') && array_key_exists('operation_type', $data)) {
            $data['operation_kind'] = $data['operation_type'];
            unset($data['operation_type']);
        }

        if (! Schema::hasColumn('operations', 'gameplay_type') && array_key_exists('gameplay_type', $data)) {
            $data['type'] = $data['gameplay_type'];
            unset($data['gameplay_type']);
        }

        if (! Schema::hasColumn('operations', 'extended_description') && array_key_exists('extended_description', $data)) {
            $data['notes'] = $data['extended_description'];
            unset($data['extended_description']);
        }

        return $data;
    }
}
