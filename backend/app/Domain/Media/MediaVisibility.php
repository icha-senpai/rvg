<?php

namespace App\Domain\Media;

use App\Domain\AccessControl\AccessService;
use App\Models\Media;
use App\Models\Squadron;
use App\Models\User;

class MediaVisibility
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function applyListVisibility(User $user, array $filters, ?int $squadronId = null): MediaVisibilityResult
    {
        $collection = (string) ($filters['collection'] ?? '');

        $isDirectorLike = $this->access->isDirectorLike($user);
        $isOfficer = $this->access->isOfficer($user);

        if ($collection === Media::COLLECTION_SHIP_IMAGE
            || $collection === Media::COLLECTION_SITE_ASSET) {
            if (! $isDirectorLike && ! $isOfficer) {
                abort(403);
            }
        }

        if ($collection === Media::COLLECTION_SQUADRON_EMBLEM && ! $isDirectorLike) {
            if (! $squadronId) {
                return new MediaVisibilityResult($filters, 'squadron_id is required for squadron emblems.');
            }

            $squadron = Squadron::find($squadronId);

            $canBrowseEmblems = $squadron
                && (
                    $this->access->isSquadronLeader($user, $squadron)
                    || (
                        $isOfficer
                        && $this->access->isSquadronMember($user, $squadron)
                    )
                );

            if (! $canBrowseEmblems) {
                abort(403);
            }
        }

        $publicCollections = [];

        if (! $isDirectorLike) {
            if (! $collection) {
                $filters['uploaded_by'] = $user->id;
            } elseif ($collection === Media::COLLECTION_OPERATION_IMAGE) {
                if (! $isOfficer) {
                    $filters['uploaded_by'] = $user->id;
                }
            } elseif ($collection === Media::COLLECTION_SHIP_IMAGE
                || $collection === Media::COLLECTION_SITE_ASSET) {
            } elseif ($collection === Media::COLLECTION_SQUADRON_EMBLEM) {
            } elseif (! in_array($collection, $publicCollections, true)) {
                $filters['uploaded_by'] = $user->id;
            }
        }

        return new MediaVisibilityResult($filters);
    }
}
