<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Squadrons\Presenters\SquadronMemberPresenter;
use App\Domain\Squadrons\Presenters\SquadronPresenter;
use App\Domain\Squadrons\SquadronService;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SquadronPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SquadronService $squadrons
    ) {}

    public function showById(Request $request, Squadron $squadron)
    {
        if ($squadron->slug) {
            return redirect()->route('squadrons.show', ['squadron' => $squadron->slug]);
        }

        return $this->renderShowPage($request, $squadron);
    }

    public function show(Request $request, Squadron $squadron)
    {
        return $this->renderShowPage($request, $squadron);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Squadron::class);

        $activeSquadron = $this->resolveActiveSquadron($request);

        return Inertia::render('Squadrons/Index', [
            'squadrons' => SquadronPresenter::collection($this->squadrons->listAll()),
            'activeSquadron' => $activeSquadron
                ? $this->presentActiveSquadron($request, $activeSquadron)
                : null,
        ]);
    }

    protected function renderShowPage(Request $request, Squadron $squadron)
    {
        return Inertia::render('Squadrons/Show', [
            'squadronId' => $squadron->id,
            'activeSquadron' => $this->presentActiveSquadron($request, $squadron),
        ]);
    }

    protected function resolveActiveSquadron(Request $request): ?Squadron
    {
        $identifier = trim((string) $request->query('squadron', ''));

        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            return Squadron::query()->findOrFail((int) $identifier);
        }

        return Squadron::query()->where('slug', $identifier)->firstOrFail();
    }

    protected function presentActiveSquadron(Request $request, Squadron $squadron): array
    {
        $this->authorize('view', $squadron);

        $user = $request->user();
        if (! $user instanceof User) {
            $user = null;
        }

        $squadron = $this->squadrons->show($squadron);

        $viewerMembership = null;
        if ($user) {
            $viewerMembership = $squadron->members->firstWhere('user_id', $user->id);
        }

        return [
            'squadron' => SquadronPresenter::make($squadron),
            'members' => SquadronMemberPresenter::collection($squadron->members),
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
                'can_demote_lieutenant' => $user
                    ? $user->can('demoteLieutenant', $squadron)
                    : false,
                'can_update_squadron' => $user
                    ? $user->can('update', $squadron)
                    : false,
                'can_apply' => $user
                    && ! $viewerMembership
                    && $squadron->recruiting,
                'can_leave' => $user
                    && $viewerMembership
                    && $viewerMembership->membership_status === \App\Models\SquadronMember::STATUS_ACTIVE,
                'can_wait' => $user
                    && $viewerMembership
                    && $viewerMembership->membership_status === \App\Models\SquadronMember::STATUS_PENDING,
            ],
        ];
    }
}
