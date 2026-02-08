<?php

namespace App\Domain\Media\Presenters;

use App\Models\Media;

class MediaPresenter
{
    protected Media $media;

    public function __construct(Media $media)
    {
        $this->media = $media;
    }

    public static function make(Media $media): self
    {
        return new self($media);
    }

    /**
     * Compact shape for lists, pickers, and grids.
     */
    public function summary(): array
    {
        return [
            'id'                => $this->media->id,
            'collection'        => $this->media->collection,
            'original_filename' => $this->media->original_filename,
            'mime_type'         => $this->media->mime_type,
            'size'              => $this->media->size,
            'human_size'        => $this->media->human_size,
            'width'             => $this->media->width,
            'height'            => $this->media->height,
            'url'               => $this->media->url,
            'thumbnail_url'     => $this->media->thumbnail_url,
            'medium_url'        => $this->media->medium_url,
            'alt_text'          => $this->media->alt_text,
            'created_at'        => $this->media->created_at?->toIso8601String(),
        ];
    }

    /**
     * Full detail shape for admin panels and detail views.
     */
    public function full(): array
    {
        $this->media->loadMissing('uploader:id,rsi_handle,discord_name');

        return [
            'id'                => $this->media->id,
            'collection'        => $this->media->collection,
            'original_filename' => $this->media->original_filename,
            'disk'              => $this->media->disk,
            'path'              => $this->media->path,
            'thumbnail_path'    => $this->media->thumbnail_path,
            'medium_path'       => $this->media->medium_path,
            'mime_type'         => $this->media->mime_type,
            'size'              => $this->media->size,
            'human_size'        => $this->media->human_size,
            'width'             => $this->media->width,
            'height'            => $this->media->height,
            'alt_text'          => $this->media->alt_text,
            'meta'              => $this->media->meta,
            'url'               => $this->media->url,
            'thumbnail_url'     => $this->media->thumbnail_url,
            'medium_url'        => $this->media->medium_url,
            'display_url'       => $this->media->display_url,

            'mediable_type'     => $this->media->mediable_type,
            'mediable_id'       => $this->media->mediable_id,

            'uploader' => $this->media->uploader
                ? $this->media->uploader->only(['id', 'rsi_handle', 'discord_name'])
                : null,

            'created_at' => $this->media->created_at?->toIso8601String(),
            'updated_at' => $this->media->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Minimal shape for embedding in other presenters (e.g., squadron emblem, avatar).
     */
    public function embedded(): array
    {
        return [
            'id'            => $this->media->id,
            'url'           => $this->media->url,
            'thumbnail_url' => $this->media->thumbnail_url,
            'medium_url'    => $this->media->medium_url,
            'alt_text'      => $this->media->alt_text,
        ];
    }
}
