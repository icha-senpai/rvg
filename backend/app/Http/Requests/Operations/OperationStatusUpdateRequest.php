<?php

namespace App\Http\Requests\Operations;

use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Enums\OperationStatus;
use Illuminate\Foundation\Http\FormRequest;

class OperationStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:' . implode(',', OperationStatus::transitionableValues()),
            'reason' => 'required_if:status,' . OperationStatus::Canceled->value . '|nullable|string|max:500',
            'outcome' => 'required_if:status,' . OperationStatus::Completed->value . '|in:' . implode(',', CompletionOutcome::values()),
        ];
    }
}
