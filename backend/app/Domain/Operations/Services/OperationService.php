<?php

namespace App\Domain\Operations\Services;

use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

// NEW imports
use App\Domain\Operations\Actions\{
    CreateOperation,
    UpdateOperation,
    CancelOperation,
    TransitionOperation
};

class OperationService
{
    /**
     * Create a new operation via Action.
     */
    public function create(array $data, ?Squadron $squadron = null): Operation
    {
        $data = $this->applyDefaults($data);

        return (new CreateOperation)->execute($data, $squadron);
    }

    /**
     * Update an existing operation.
     */
    public function update(Operation $operation, array $data): Operation
    {
        $data = $this->applyDefaults($data);

        return (new UpdateOperation)->execute($operation, $data);
    }

    /**
     * Cancel an operation (soft delete).
     */
    public function cancel(Operation $operation, ?string $reason = null): Operation
    {
        return (new CancelOperation)->execute($operation, $reason);
    }

    /**
     * State machine transition using Action + wrapped error normalization.
     */
    public function transition(Operation $operation, string $status, ?string $reason = null): Operation
    {
        try {
            return (new TransitionOperation)->execute($operation, $status, $reason);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalization layer stays — this belongs in the domain!
     */
    protected function applyDefaults(array $data): array
    {
        $data['visibility'] = $data['visibility'] ?? 'open';

        if (array_key_exists('slots', $data) && empty($data['slots'])) {
            $data['slots'] = [];
        }

        return $data;
    }

    /**
     * Relationship hydration stays as a convenience method.
     */
    public function loadGraph(Operation $operation): Operation
    {
        return $operation->load([
            'squadron',
            'creator',
            'participants.user',
            'roles.participants.user',
        ]);
    }
}
