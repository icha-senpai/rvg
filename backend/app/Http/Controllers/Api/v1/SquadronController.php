<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Http\Requests\SquadronStoreRequest;
use App\Http\Requests\SquadronUpdateRequest;
use App\Domain\Squadrons\SquadronService;
use App\Domain\Squadrons\Presenters\SquadronPresenter;
use App\Domain\Squadrons\Presenters\SquadronMemberPresenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SquadronController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SquadronService $squadrons
    ) {}

    /**
     * GET /api/v1/squadrons
     */
    public function index()
    {
        $this->authorize('viewAny', Squadron::class);

        return SquadronPresenter::collection(
            $this->squadrons->listAll()
        );
    }

    /**
     * GET /api/v1/squadrons/{squadron}
     */
    public function show(Squadron $squadron)
    {
        $this->authorize('view', $squadron);

        return SquadronPresenter::make(
            $this->squadrons->show($squadron)
        );
    }

    /**
     * POST /api/v1/squadrons
     */
    public function store(SquadronStoreRequest $request)
    {
        $this->authorize('create', Squadron::class);

        $squadron = $this->squadrons->create($request->validated());

        return response()->json(
            SquadronPresenter::make($squadron),
            201
        );
    }

    /**
     * PUT /api/v1/squadrons/{squadron}
     */
    public function update(SquadronUpdateRequest $request, Squadron $squadron)
    {
        $this->authorize('update', $squadron);

        $updated = $this->squadrons->update($squadron, $request->validated());

        return response()->json(
            SquadronPresenter::make($updated)
        );
    }

    /**
     * DELETE /api/v1/squadrons/{squadron}
     */
    public function destroy(Squadron $squadron)
    {
        $this->authorize('delete', $squadron);

        $this->squadrons->delete($squadron);

        return response()->json(['message' => 'Squadron deleted']);
    }

    /**
     * GET /api/v1/squadrons/{squadron}/members
     */
    public function members(Squadron $squadron)
    {
        $this->authorize('view', $squadron);

        $members = $this->squadrons->members($squadron);

        return SquadronMemberPresenter::collection($members);
    }
}
