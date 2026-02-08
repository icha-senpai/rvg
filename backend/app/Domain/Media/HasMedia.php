<?php

namespace App\Domain\Media;

use App\Models\Media;

/**
 * Add this trait to any Eloquent model that can have media attached.
 *
 * Provides:
 *   $model->media()              — all attached media
 *   $model->mediaInCollection()  — filtered by collection
 *   $model->latestMedia()        — most recent attachment in a collection
 */
trait HasMedia
{
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function mediaInCollection(string $collection)
    {
        return $this->media()->where('collection', $collection);
    }

    public function latestMedia(string $collection): ?Media
    {
        return $this->media()
            ->where('collection', $collection)
            ->latest()
            ->first();
    }
}
