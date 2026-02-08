<?php

namespace App\Policies;

use App\Models\Media;
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
        // Directors see everything
        if ($this->access->isDirectorLike($user)) {
            return true;
        }

        // Users can always see their own uploads
        if ($media->uploaded_by === $user->id) {
            return true;
        }

        // Public collections are visible to all authenticated users
        $publicCollections = [
            Media::COLLECTION_SQUADRON_EMBLEM,
            Media::COLLECTION_OPERATION_IMAGE,
            Media::COLLECTION_SHIP_IMAGE,
            Media::COLLECTION_SITE_ASSET,
        ];

        return in_array($media->collection, $publicCollections, true);
    }

    /**
     * Can the user upload to a given collection?
     *
     * Collection is passed as a string via the second argument.
     */
    public function upload(User $user, ?string $collection = null): bool
    {
        // Directors and tech directors can upload to any collection
        if ($this->access->isDirectorLike($user)) {
            return true;
        }

        return match ($collection) {
            // Any verified member can upload their own avatar
            Media::COLLECTION_AVATAR => true,

            // Squadron leaders and lieutenants can upload emblems
            // (squadron-level check happens in the controller)
            Media::COLLECTION_SQUADRON_EMBLEM => true,

            // Operation creators can attach images
            // (operation-level check happens in the controller)
            Media::COLLECTION_OPERATION_IMAGE => true,

            // Ship images: directors only
            Media::COLLECTION_SHIP_IMAGE => false,

            // Site assets: directors only
            Media::COLLECTION_SITE_ASSET => false,

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
