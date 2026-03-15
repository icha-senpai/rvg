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

                if ($this->access->isSquadronLeader($user, $squadron)) {
                    return true;
                }

                if (! $this->access->isOfficer($user)) {
                    return false;
                }

                return $this->access->isSquadronMember($user, $squadron);
            }

            return $this->access->isOfficer($user);
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
            return $this->access->isOfficer($user);
        }

        if ($media->collection === Media::COLLECTION_OPERATION_IMAGE) {
            return $this->access->isOfficer($user);
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

            if ($this->access->isSquadronLeader($user, $squadron)) {
                return true;
            }

            if (! $this->access->isOfficer($user)) {
                return false;
            }

            return $this->access->isSquadronMember($user, $squadron);
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
            Media::COLLECTION_OPERATION_IMAGE => $this->access->isOfficer($user),

            // Ship images: rank level 2+ (or director-like above)
            Media::COLLECTION_SHIP_IMAGE => $this->access->isOfficer($user),

            // Site assets: rank level 2+ (or director-like above)
            Media::COLLECTION_SITE_ASSET => $this->access->isOfficer($user),

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
