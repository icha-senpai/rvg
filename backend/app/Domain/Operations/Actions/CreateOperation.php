<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;
use App\Domain\Operations\Actions\JoinOperation;
use Illuminate\Support\Facades\Auth;
use App\Domain\Operations\Events\OperationPublished; // <-- ADD THIS if using events

class CreateOperation
{
    public function execute(array $data, ?Squadron $squadron = null): Operation
    {
        // Determine status
        $status = in_array($data['status'] ?? null, ['draft', 'published'])
            ? $data['status']
            : 'draft';

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

        // 🔥 FIRE PUBLISH CODE IF NEEDED
        if ($status === 'published') {
            // If you're using Laravel Events:
            event(new OperationPublished($operation));

            // OR if you're hitting the bot directly:
            // Http::post(env('BOT_WEBHOOK_URL'), [
            //     'operation_id' => $operation->id,
            //     'title' => $operation->title,
            //     ...
            // ]);
        }

        return $operation;
    }
}
