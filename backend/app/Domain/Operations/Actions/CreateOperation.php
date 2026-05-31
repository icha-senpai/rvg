<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Events\OperationPublished;
use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CreateOperation
{
    public function execute(array $data, ?Squadron $squadron = null): Operation
    {
        $status = in_array($data['status'] ?? null, OperationStatus::creatableValues(), true)
            ? $data['status']
            : OperationStatus::Draft->value;

        $creatorId = Auth::id();

        $operation = Operation::create([
            ...$this->normalizeDatabaseColumns($data),
            'squadron_id' => $squadron?->id,
            'created_by'  => $creatorId,
            'status'      => $status,
        ]);

        if ($creatorId) {
            User::whereKey($creatorId)->increment('operations_created_count');

            $creator = Auth::user();

            if ($creator && ! $operation->participants()->where('user_id', $creator->id)->exists()) {
                (new JoinOperation)->execute($operation, $creator, []);
            }
        }

        if ($status === OperationStatus::Published->value) {
            OperationPublished::dispatch($operation->fresh());
        }

        return $operation;
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
