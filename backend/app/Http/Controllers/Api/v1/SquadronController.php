<?php

namespace App\Http\Controllers\Api\v1;

use App\Application\Squadrons\Presenters\SquadronMemberPresenter;
use App\Application\Squadrons\Presenters\SquadronPresenter;
use App\Domain\AccessControl\AccessService;
use App\Domain\Media\MediaService;
use App\Domain\Squadrons\SquadronService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SquadronStoreRequest;
use App\Http\Requests\SquadronUpdateRequest;
use App\Models\Media;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

/**
 * JSON API controller for squadron listing, detail, mutation, and membership
 * list endpoints.
 */
class SquadronController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SquadronService $squadrons,
        protected MediaService $media,
        protected AccessService $access
    ) {}

    /**
     * Return the public squadron list, applying the normal policy when an
     * authenticated viewer is present.
     */
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

    /**
     * Return the full squadron payload together with viewer-specific permission
     * flags and the viewer's own membership record when present.
     */
    public function show(Squadron $squadron)
    {
        $this->authorize('view', $squadron);

        $user = Auth::user();
        if (! $user instanceof User) {
            $user = null;
        }

        $squadron = $this->squadrons->show($squadron);

        $viewerMembership = null;
        if ($user) {
            $viewerMembership = $squadron->members
                ->firstWhere('user_id', $user->id);
        }

        return response()->json([
            'status' => 'success',
            'message' => null,
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
        ]);
    }

    /**
     * Create a new squadron.
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
     * Update a squadron and optionally replace or clear its emblem media.
     */
    public function update(SquadronUpdateRequest $request, Squadron $squadron)
    {
        $data = $request->validated();
        $emblemMediaId = $data['emblem_media_id'] ?? null;
        $emblemKeyExists = array_key_exists('emblem_media_id', $data);
        unset($data['emblem_media_id']);

        $hasOtherUpdates = count($data) > 0;

        if ($hasOtherUpdates) {
            $this->authorize('update', $squadron);
        } else {
            $this->authorize('view', $squadron);
        }

        if ($emblemKeyExists) {
            $user = Auth::user();

            if (! ($user instanceof User)) {
                abort(403);
            }

            $canUpdateEmblem = $this->access->isDirectorLike($user)
                || $this->access->isSquadronLeader($user, $squadron)
                || (
                    $this->access->isOfficer($user)
                    && $this->access->isSquadronMember($user, $squadron)
                );

            if (! $canUpdateEmblem) {
                abort(403);
            }
        }

        $squadron = $this->squadrons->update($squadron, $data);

        if ($emblemKeyExists) {
            if ($emblemMediaId === null) {
                Media::where('mediable_type', Squadron::class)
                    ->where('mediable_id', $squadron->id)
                    ->where('collection', Media::COLLECTION_SQUADRON_EMBLEM)
                    ->update([
                        'mediable_type' => null,
                        'mediable_id' => null,
                    ]);
            } else {
                $media = Media::findOrFail((int) $emblemMediaId);

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

    /**
     * Delete a squadron through the API.
     */
    public function destroy(Squadron $squadron)
    {
        $this->authorize('delete', $squadron);

        $this->squadrons->delete($squadron);

        return response()->json([
            'status' => 'success',
            'message' => 'Squadron deleted',
        ]);
    }

    /**
     * Return the presented member list for one squadron.
     */
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
