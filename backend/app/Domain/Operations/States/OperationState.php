<?php

namespace App\Domain\Operations\States;

use App\Models\Operation;
use Illuminate\Validation\ValidationException;

abstract class OperationState
{
    protected Operation $operation;

    public function __construct(Operation $operation)
    {
        $this->operation = $operation;
    }

    /**
     * Human / internal name for this state.
     */
    abstract public static function name(): string;

    /**
     * Which statuses this state is allowed to transition to.
     *
     * @return string[]
     */
    abstract public function allowedTransitions(): array;

    /**
     * Build the correct state object from the Operation's current status.
     */
    public static function from(Operation $operation): self
    {
        return match ($operation->status) {
            'draft'       => new Draft($operation),
            'published'   => new Published($operation),
            'in_progress' => new InProgress($operation),
            'completed'   => new Completed($operation),
            'canceled'    => new Canceled($operation),
            default       => throw new \RuntimeException("Unknown operation status: {$operation->status}"),
        };
    }

    /**
     * Perform a state transition, validating if it's allowed.
     */
    public function transitionTo(string $targetStatus, ?string $reason = null): Operation
    {
        if (! in_array($targetStatus, $this->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid transition from ".static::name()." to {$targetStatus}",
            ]);
        }

        $this->operation->status = $targetStatus;

        if ($targetStatus === 'canceled' && $reason) {
            $this->operation->cancellation_reason = $reason;
        }

        $this->operation->save();

        return $this->operation->fresh();
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }
}
