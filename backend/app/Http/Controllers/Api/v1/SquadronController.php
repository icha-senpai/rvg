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

    /** GET /api/v1/squadrons */
    public function index()
    {
        $this->authorize('viewAny', Squadron::class);

        return response()->json(
            SquadronPresenter::collection(
                $this->squadrons->listAll()
            )
        );
    }

    /** GET /api/v1/squadrons/{squadron} */
    public function show(Squadron $squadron)
    {
        $this->authorize('view', $squadron);

        $user = auth()->user();

        // Load full graph
        $squadron = $this->squadrons->show($squadron);

        // Viewer membership (user can only be in one squadron anyway)
        $viewerMembership = null;
        if ($user) {
            $viewerMembership = $squadron->members
                ->firstWhere('user_id', $user->id);
        }

        return response()->json([
            'squadron' => \App\Domain\Squadrons\Presenters\SquadronPresenter::make($squadron),

            'members' => \App\Domain\Squadrons\Presenters\SquadronMemberPresenter::collection(
                $squadron->members
            ),

            'viewer_membership' => $viewerMembership
                ? SquadronMemberPresenter::make($viewerMembership)
                : null,

            'permissions' => [
                'can_manage_members' => $user
                    ? $user->can('manageMembers', $squadron)
                    : false,

                'can_promote_lieutenant' => $user
                    ? $user->can('promoteLieutenant', $squadron)
                    : false,

                // derived UI permissions
                'can_apply' => $user
                    && !$viewerMembership
                    && $squadron->recruiting,

                'can_leave' => $user
                    && $viewerMembership
                    && $viewerMembership->membership_status === \App\Models\SquadronMember::STATUS_ACTIVE,

                'can_wait' => $user
                    && $viewerMembership
                    && $viewerMembership->membership_status === \App\Models\SquadronMember::STATUS_PENDING,
            ],
        ]);
    }


    /** POST /api/v1/squadrons */
    public function store(SquadronStoreRequest $request)
    {
        $this->authorize('create', Squadron::class);

        $squadron = $this->squadrons->create($request->validated());

        return response()->json(
            SquadronPresenter::make($squadron),
            201
        );
    }

    /** PUT /api/v1/squadrons/{squadron} */
    public function update(SquadronUpdateRequest $request, Squadron $squadron)
    {
        $this->authorize('update', $squadron);

        return response()->json(
            SquadronPresenter::make(
                $this->squadrons->update($squadron, $request->validated())
            )
        );
    }

    /** DELETE /api/v1/squadrons/{squadron} */
    public function destroy(Squadron $squadron)
    {
        $this->authorize('delete', $squadron);

        $this->squadrons->delete($squadron);

        return response()->json(['message' => 'Squadron deleted']);
    }

    /** GET /api/v1/squadrons/{squadron}/members */
    public function members(Squadron $squadron)
    {
        $this->authorize('view', $squadron);

        return response()->json(
            SquadronMemberPresenter::collection(
                $this->squadrons->members($squadron)
            )
        );
    }
}
