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
use App\Domain\AccessControl\AccessService;
use App\Domain\Media\MediaService;
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

        // Load the related squadron graph once so the presenter and permission
        // payload can reuse the same in-memory relationships.
        $squadron = $this->squadrons->show($squadron);

        // The viewer can only have one membership row in this squadron, so the
        // first matching member record is enough for the UI payload.
        $viewerMembership = null;
        if ($user) {
            $viewerMembership = $squadron->members
                ->firstWhere('user_id', $user->id);
        }

        return response()->json([
            'status' => 'success',
            'message' => null,
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

                // These convenience flags keep the frontend from having to repeat
                // basic squadron membership-state checks.
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
     *
     * Non-emblem updates use the normal squadron update policy. Emblem changes
     * also require the specialized emblem-management capability check.
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

            // Emblem changes have their own authorization rule because some users
            // may manage media without having full squadron-edit access.
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
                // Clearing the emblem detaches the current emblem media without
                // deleting the media record itself.
                Media::where('mediable_type', Squadron::class)
                    ->where('mediable_id', $squadron->id)
                    ->where('collection', Media::COLLECTION_SQUADRON_EMBLEM)
                    ->update([
                        'mediable_type' => null,
                        'mediable_id'   => null,
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

                // Reuse the shared media attach flow so emblem replacement follows
                // the same rules as the rest of the media domain.
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
