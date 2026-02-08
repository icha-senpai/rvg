<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /* ------------------------------------------
     | COLLECTION CONSTANTS
     ------------------------------------------ */
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

    /* ------------------------------------------
     | ALLOWED MIME TYPES
     ------------------------------------------ */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    /* ------------------------------------------
     | SIZE LIMIT (50 MB in bytes)
     ------------------------------------------ */
    public const MAX_SIZE_BYTES = 52_428_800;

    /* ------------------------------------------
     | FILLABLE
     ------------------------------------------ */
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

    /* ------------------------------------------
     | RELATIONSHIPS
     ------------------------------------------ */

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Polymorphic parent: User, Squadron, Operation, etc.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    /* ------------------------------------------
     | URL ACCESSORS
     ------------------------------------------ */

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->thumbnail_path);
    }

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

    /* ------------------------------------------
     | SCOPES
     ------------------------------------------ */

    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    public function scopeUploadedBy($query, int $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    public function scopeAttachedTo($query, Model $entity)
    {
        return $query->where('mediable_type', get_class($entity))
                     ->where('mediable_id', $entity->id);
    }

    public function scopeUnattached($query)
    {
        return $query->whereNull('mediable_type')
                     ->whereNull('mediable_id');
    }

    /* ------------------------------------------
     | HELPERS
     ------------------------------------------ */

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isSvg(): bool
    {
        return $this->mime_type === 'image/svg+xml';
    }

    public function isAnimated(): bool
    {
        return in_array($this->mime_type, ['image/gif', 'image/webp']);
    }

    /**
     * Human-readable file size.
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
