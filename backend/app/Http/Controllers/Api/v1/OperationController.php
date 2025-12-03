<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\Squadron;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperationController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Operation::class);

        return Operation::with(['squadron', 'creator'])
            ->orderBy('starts_at')
            ->get();
    }

    public function show(Operation $operation)
    {
        $this->authorize('view', $operation);

        return $operation->load([
            'squadron',
            'creator',
            'roles.participants.user',
            'participants.user',
        ]);
    }

    public function store(Request $request, ?Squadron $squadron = null)
    {
        $this->authorize('create', Operation::class);

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'starts_at'          => 'required|date',
            'ends_at'            => 'nullable|date|after:starts_at',

            'operation_kind'     => 'required|in:event,mission',
            'type'               => 'nullable|string|max:255',

            'visibility'         => 'nullable|in:open,squadron,private',
            'difficulty'         => 'nullable|in:low,medium,high',
            'operation_strictness'=> 'nullable|in:casual,normal,strict,roleplay',

            'icon'               => 'nullable|string|max:20',
            'image_url'          => 'nullable|url',
            'rsvp_deadline'      => 'nullable|date|before:starts_at',
            'notes'              => 'nullable|string|max:2000',
            'slots'              => 'nullable|array',
        ]);

        $operation = Operation::create([
            ...$data,
            'visibility'   => $data['visibility'] ?? 'open',
            'status'       => 'draft',
            'squadron_id'  => $squadron?->id,
            'created_by'   => auth()->id(),
        ]);

        return response()->json($operation, 201);
    }

    public function update(Request $request, Operation $operation)
    {
        $this->authorize('update', $operation);

        $data = $request->validate([
            'title'              => 'sometimes|string|max:255',
            'description'        => 'sometimes|string',
            'starts_at'          => 'sometimes|date',
            'ends_at'            => 'sometimes|date|after:starts_at',

            'operation_kind'     => 'sometimes|in:event,mission',
            'type'               => 'sometimes|string|max:255',

            'visibility'         => 'sometimes|in:open,squadron,private',
            'difficulty'         => 'sometimes|in:low,medium,high',
            'operation_strictness'=> 'sometimes|in:casual,normal,strict,roleplay',

            'icon'               => 'sometimes|string|max:20',
            'image_url'          => 'sometimes|url',
            'rsvp_deadline'      => 'sometimes|date|before:starts_at',
            'notes'              => 'sometimes|string|max:2000',
            'slots'              => 'sometimes|array',

            'status'             => 'sometimes|in:draft,published,in_progress,completed,canceled',
        ]);

        $operation->update($data);

        return response()->json($operation);
    }

    public function destroy(Operation $operation)
    {
        $this->authorize('delete', $operation);

        $operation->update(['status' => 'canceled']);
        $operation->delete();

        return response()->json(['message' => 'Operation canceled']);
    }

    public function updateStatus(Request $request, Operation $operation)
    {
        $this->authorize('manage', $operation);

        $validated = $request->validate([
            'status' => 'required|in:published,in_progress,completed,canceled',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $operation->transitionTo(
                $validated['status'],
                $validated['reason'] ?? null
            );

            return response()->json([
                'message'   => 'Operation status updated',
                'operation' => $operation->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
