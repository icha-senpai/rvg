<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Events\OperationPublished;
use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CreateOperation
{
    public function execute(array $data, ?Squadron $squadron = null): Operation
    {
        $status = in_array($data['status'] ?? null, OperationStatus::creatableValues(), true)
            ? $data['status']
            : OperationStatus::Draft->value;

        $creatorId = Auth::id();

        $operation = Operation::create([
            ...$data,
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
}
