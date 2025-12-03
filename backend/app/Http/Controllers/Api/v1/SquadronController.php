<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Http\Requests\SquadronStoreRequest;
use App\Http\Requests\SquadronUpdateRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class SquadronController extends Controller
{
    use AuthorizesRequests;
    /**
     * List all squadrons.
     */
    public function index()
    {
        $this->authorize('viewAny', Squadron::class);

        return Squadron::withCount('members')->get();
    }

    /**
     * Show a single squadron.
     */
    public function show(Squadron $squadron)
    {
        $this->authorize('view', $squadron);

        return $squadron->load('members.user');
    }

    /**
     * Create a squadron.
     */
    public function store(SquadronStoreRequest $request)
    {
        $this->authorize('create', Squadron::class);

        $squadron = Squadron::create($request->validated());

        return response()->json($squadron, 201);
    }

    /**
     * Update a squadron.
     */
    public function update(SquadronUpdateRequest $request, Squadron $squadron)
    {
        $this->authorize('update', $squadron);

        $squadron->update($request->validated());

        return response()->json($squadron);
    }

    /**
     * Delete a squadron.
     */
    public function destroy(Squadron $squadron)
    {
        $this->authorize('delete', $squadron);

        $squadron->delete();

        return response()->json(['message' => 'Squadron deleted']);
    }

    /**
     * View members of a squadron.
     */
    public function members(Squadron $squadron)
    {
        $this->authorize('view', $squadron);

        return $squadron->members()->with('user')->get();
    }
}
