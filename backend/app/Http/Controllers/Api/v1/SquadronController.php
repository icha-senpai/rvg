<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Squadron;
use App\Models\Media;
use App\Http\Requests\SquadronStoreRequest;
use App\Http\Requests\SquadronUpdateRequest;
use App\Domain\Squadrons\SquadronService;
use App\Domain\Squadrons\Presenters\SquadronPresenter;
use App\Domain\Squadrons\Presenters\SquadronMemberPresenter;
use App\Domain\Media\MediaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class SquadronController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SquadronService $squadrons,
        protected MediaService $media
    ) {}

    /** GET /api/v1/squadrons */
    public function index()
    {
        if (Auth::check()) {
            $this->authorize('viewAny', Squadron::class);
        }

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

        $user = Auth::user();

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

        $data = $request->validated();
        $emblemMediaId = $data['emblem_media_id'] ?? null;
        $emblemKeyExists = array_key_exists('emblem_media_id', $data);
        unset($data['emblem_media_id']);

        $squadron = $this->squadrons->update($squadron, $data);

        if ($emblemKeyExists) {
            if ($emblemMediaId === null) {
                Media::where('mediable_type', Squadron::class)
                    ->where('mediable_id', $squadron->id)
                    ->where('collection', Media::COLLECTION_SQUADRON_EMBLEM)
                    ->update([
                        'mediable_type' => null,
                        'mediable_id'   => null,
                    ]);
            } else {
                $media = Media::findOrFail((int) $emblemMediaId);

                if ($media->collection !== Media::COLLECTION_SQUADRON_EMBLEM) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Selected media is not a squadron emblem.',
                    ], 422);
                }

                if ($media->mediable_type && ! (
                    $media->mediable_type === Squadron::class
                    && (int) $media->mediable_id === (int) $squadron->id
                )) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Selected media is already attached to another record.',
                    ], 422);
                }

                $this->media->attach($media, $squadron, true);
            }
        }

        $squadron = $this->squadrons->show($squadron);

        return response()->json(
            SquadronPresenter::make($squadron)
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
