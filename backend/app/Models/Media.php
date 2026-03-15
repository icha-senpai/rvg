<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Represents one uploaded media asset together with its derived variants and
 * optional polymorphic attachment.
 */
class Media extends Model
{
    public const COLLECTION_AVATAR          = 'avatar';
    public const COLLECTION_SQUADRON_EMBLEM = 'squadron_emblem';
    public const COLLECTION_OPERATION_IMAGE = 'operation_image';
    public const COLLECTION_SHIP_IMAGE      = 'ship_image';
    public const COLLECTION_SITE_ASSET      = 'site_asset';

    public const COLLECTIONS = [
        self::COLLECTION_AVATAR,
        self::COLLECTION_SQUADRON_EMBLEM,
        self::COLLECTION_OPERATION_IMAGE,
        self::COLLECTION_SHIP_IMAGE,
        self::COLLECTION_SITE_ASSET,
    ];

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * Maximum upload size in bytes.
     */
    public const MAX_SIZE_BYTES = 52_428_800;

    protected $fillable = [
        'uploaded_by',
        'collection',
        'original_filename',
        'disk',
        'path',
        'thumbnail_path',
        'medium_path',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'mediable_type',
        'mediable_id',
        'meta',
    ];

    protected $casts = [
        'size'   => 'integer',
        'width'  => 'integer',
        'height' => 'integer',
        'meta'   => 'array',
    ];

    /**
     * Return the user who uploaded this media record.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Return the polymorphic owner of this media asset when it is attached.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    /**
     * Return the public URL for the original stored file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Return the public URL for the thumbnail variant when one exists.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->thumbnail_path);
    }

    /**
     * Return the public URL for the medium-size variant when one exists.
     */
    public function getMediumUrlAttribute(): ?string
    {
        if (! $this->medium_path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->medium_path);
    }

    /**
     * Returns the best available URL for display:
     * medium if exists, otherwise original.
     */
    public function getDisplayUrlAttribute(): string
    {
        return $this->medium_url ?? $this->url;
    }

    /**
     * Scope the query to one media collection.
     */
    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    /**
     * Scope the query to media uploaded by one user.
     */
    public function scopeUploadedBy($query, int $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Scope the query to media attached to the given polymorphic entity.
     */
    public function scopeAttachedTo($query, Model $entity)
    {
        return $query->where('mediable_type', get_class($entity))
                     ->where('mediable_id', $entity->id);
    }

    /**
     * Scope the query to unattached media records.
     */
    public function scopeUnattached($query)
    {
        return $query->whereNull('mediable_type')
                     ->whereNull('mediable_id');
    }

    /**
     * Check whether the stored asset is an image-type file.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check whether this record refers to a legacy SVG asset.
     */
    public function isSvg(): bool
    {
        return $this->mime_type === 'image/svg+xml';
    }

    /**
     * Check whether the image format may contain animation.
     */
    public function isAnimated(): bool
    {
        return in_array($this->mime_type, ['image/gif', 'image/webp'], true);
    }

    /**
     * Return the stored file size in a human-readable format.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
