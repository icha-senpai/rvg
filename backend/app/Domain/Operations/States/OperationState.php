<?php

namespace App\Domain\Operations\States;

use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
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
    abstract public static function name(): OperationStatus;

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
        return match (OperationStatus::from($operation->status)) {
            OperationStatus::Draft => new Draft($operation),
            OperationStatus::Published => new Published($operation),
            OperationStatus::InProgress => new InProgress($operation),
            OperationStatus::Completed => new Completed($operation),
            OperationStatus::Canceled => new Canceled($operation),
            default       => throw new \RuntimeException("Unknown operation status: {$operation->status}"),
        };
    }

    /**
     * Perform a state transition, validating if it's allowed.
     */
    public function transitionTo(string $targetStatus, ?string $reason = null, ?string $outcome = null): Operation
    {
        $target = OperationStatus::from($targetStatus);

        if (! in_array($targetStatus, $this->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid transition from ' . static::name()->value . " to {$target->value}",
            ]);
        }

        $this->operation->status = $target->value;

        if ($target === OperationStatus::Canceled && $reason) {
            $this->operation->cancellation_reason = $reason;
        }

        if ($target === OperationStatus::Completed) {
            if ($outcome !== null && ! in_array($outcome, CompletionOutcome::values(), true)) {
                throw ValidationException::withMessages([
                    'outcome' => 'Invalid completion outcome.',
                ]);
            }

            $this->operation->completion_outcome = $outcome;
        }

        $this->operation->save();

        return $this->operation->fresh();
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }
}
