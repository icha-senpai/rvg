<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Actions\CancelOperation;
use App\Domain\Operations\Actions\CreateOperation;
use App\Domain\Operations\Actions\TransitionOperation;
use App\Domain\Operations\Actions\UpdateOperation;
use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates operation actions while keeping shared normalization and error
 * handling rules in one domain service.
 */
class OperationService
{
    /**
     * Create a new operation after applying shared defaults to the payload.
     */
    public function create(array $data, ?Squadron $squadron = null): Operation
    {
        $data = $this->applyDefaults($data);

        return (new CreateOperation)->execute($data, $squadron);
    }

    /**
     * Update an existing operation after applying the same shared defaults used
     * during creation.
     */
    public function update(Operation $operation, array $data): Operation
    {
        $data = $this->applyDefaults($data);

        return (new UpdateOperation)->execute($operation, $data);
    }

    /**
     * Cancel an operation through the dedicated cancel action.
     */
    public function cancel(Operation $operation, ?string $reason = null): Operation
    {
        return (new CancelOperation)->execute($operation, $reason);
    }

    /**
     * Transition an operation through the state machine and re-map low-level
     * transition errors into a validation-style response shape.
     */
    public function transition(Operation $operation, string $status, ?string $reason = null, ?string $outcome = null): Operation
    {
        try {
            return (new TransitionOperation)->execute($operation, $status, $reason, $outcome);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply shared defaults so create and update flows behave the same way when
     * optional fields are omitted.
     */
    protected function applyDefaults(array $data): array
    {
        $data['visibility'] = $data['visibility'] ?? 'open';

        // Empty slot payloads are normalized to an array so downstream actions do
        // not have to branch on null, empty string, or missing input.
        if (array_key_exists('slots', $data) && empty($data['slots'])) {
            $data['slots'] = [];
        }

        return $data;
    }

    /**
     * Load the related records needed by full operation presenters and detail
     * screens.
     */
    public function loadGraph(Operation $operation): Operation
    {
        return $operation->load([
            'squadron',
            'creator',
            'creator.roles',
            'participants.user',
            'roles.participants.user',
        ]);
    }
}
