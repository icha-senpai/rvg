<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\Squadron;
use App\Models\User;
use App\Domain\AccessControl\AccessService;

class MediaPolicy
{
    protected AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    /**
     * Can the user view the media library?
     * Directors and tech directors see everything.
     * Regular users see only their own uploads.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Can the user view a specific media record?
     */
    public function view(User $user, Media $media): bool
    {
        if ($media->collection === Media::COLLECTION_SQUADRON_EMBLEM) {
            if ($this->access->isDirectorLike($user)) {
                return true;
            }

            if ($media->mediable_type === Squadron::class && $media->mediable_id) {
                $squadron = Squadron::find((int) $media->mediable_id);
                if (! $squadron) {
                    return false;
                }

                if ($user->isSquadronLeader($squadron)) {
                    return true;
                }

                if ((int) ($user->rank_level ?? 0) < 2) {
                    return false;
                }

                return $user->squadronMemberships()
                    ->active()
                    ->where('squadron_id', $squadron->id)
                    ->exists();
            }

            return (int) ($user->rank_level ?? 0) >= 2;
        }

        // Directors see everything
        if ($this->access->isDirectorLike($user)) {
            return true;
        }

        // Users can always see their own uploads
        if ($media->uploaded_by === $user->id) {
            return true;
        }

        if ($media->collection === Media::COLLECTION_SHIP_IMAGE
            || $media->collection === Media::COLLECTION_SITE_ASSET) {
            return (int) ($user->rank_level ?? 0) >= 2;
        }

        if ($media->collection === Media::COLLECTION_OPERATION_IMAGE) {
            return (int) ($user->rank_level ?? 0) >= 2;
        }

        // Public collections are visible to all authenticated users
        $publicCollections = [];

        return in_array($media->collection, $publicCollections, true);
    }

    /**
     * Can the user upload to a given collection?
     *
     * Collection is passed as a string via the second argument.
     */
    public function upload(User $user, ?string $collection = null, ?int $squadronId = null): bool
    {
        if ($collection === Media::COLLECTION_SQUADRON_EMBLEM) {
            if ($this->access->isDirectorLike($user)) {
                return true;
            }

            if (! $squadronId) {
                return false;
            }

            $squadron = Squadron::find($squadronId);
            if (! $squadron) {
                return false;
            }

            if ($user->isSquadronLeader($squadron)) {
                return true;
            }

            if ((int) ($user->rank_level ?? 0) < 2) {
                return false;
            }

            return $user->squadronMemberships()
                ->active()
                ->where('squadron_id', $squadron->id)
                ->exists();
        }

        // Directors and tech directors can upload to any collection (except emblems)
        if ($this->access->isDirectorLike($user)) {
            return true;
        }

        return match ($collection) {
            // Any verified member can upload their own avatar
            Media::COLLECTION_AVATAR => true,

            // Squadron emblems handled above (needs squadron context)
            Media::COLLECTION_SQUADRON_EMBLEM => false,

            // Operation creators can attach images
            // (operation-level check happens in the controller)
            Media::COLLECTION_OPERATION_IMAGE => (int) ($user->rank_level ?? 0) >= 2,

            // Ship images: rank level 2+ (or director-like above)
            Media::COLLECTION_SHIP_IMAGE => (int) ($user->rank_level ?? 0) >= 2,

            // Site assets: rank level 2+ (or director-like above)
            Media::COLLECTION_SITE_ASSET => (int) ($user->rank_level ?? 0) >= 2,

            default => false,
        };
    }

    /**
     * Can the user delete a media record?
     */
    public function delete(User $user, Media $media): bool
    {
        // Directors can delete anything
        if ($this->access->isDirectorLike($user)) {
            return true;
        }

        // Users can delete their own avatars
        if ($media->collection === Media::COLLECTION_AVATAR
            && $media->uploaded_by === $user->id) {
            return true;
        }

        // Users cannot delete media they don't own in other collections
        return false;
    }

    /**
     * Can the user update media metadata (alt_text, collection reassignment)?
     */
    public function update(User $user, Media $media): bool
    {
        // Directors can update anything
        if ($this->access->isDirectorLike($user)) {
            return true;
        }

        // Users can update their own uploads
        return $media->uploaded_by === $user->id;
    }
}
