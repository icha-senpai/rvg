<?php

namespace App\Domain\Media\Actions;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AttachMedia
{
    /**
     * Attach a media record to a parent entity.
     *
     * For single-attachment collections (avatar, squadron_emblem),
     * this detaches any previous media of the same collection from
     * the same entity. It does NOT delete the old file — that is a
     * separate decision for the caller.
     */
    public function execute(Media $media, Model $entity, bool $replacePrevious = true): Media
    {
        $singleAttachmentCollections = [
            Media::COLLECTION_AVATAR,
            Media::COLLECTION_SQUADRON_EMBLEM,
        ];

        if ($replacePrevious && in_array($media->collection, $singleAttachmentCollections, true)) {
            // Detach (but don't delete) any previous media of the same collection on this entity
            Media::where('mediable_type', get_class($entity))
                ->where('mediable_id', $entity->id)
                ->where('collection', $media->collection)
                ->where('id', '!=', $media->id)
                ->update([
                    'mediable_type' => null,
                    'mediable_id'   => null,
                ]);
        }

        $media->update([
            'mediable_type' => get_class($entity),
            'mediable_id'   => $entity->id,
        ]);

        return $media->fresh();
    }
}
