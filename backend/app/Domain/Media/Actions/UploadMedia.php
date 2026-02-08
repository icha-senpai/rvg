<?php

namespace App\Domain\Media\Actions;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class UploadMedia
{
    protected const THUMBNAIL_WIDTH = 200;
    protected const MEDIUM_WIDTH    = 800;

    /**
     * Upload a file, generate variants, and return the Media record.
     *
     * @param  UploadedFile  $file        The uploaded file from the request.
     * @param  User          $uploader    Who is uploading.
     * @param  string        $collection  One of Media::COLLECTIONS.
     * @param  array         $options     Optional: alt_text, mediable_type, mediable_id, meta.
     */
    public function execute(
        UploadedFile $file,
        User $uploader,
        string $collection,
        array $options = []
    ): Media {
        $this->validate($file, $collection);

        $disk = 'public';
        $basePath = $this->buildBasePath($collection, $uploader->id);
        $uniqueName = $this->generateFilename($file);

        // Store the original file
        $originalPath = $file->storeAs($basePath, $uniqueName, $disk);

        if (! $originalPath) {
            throw ValidationException::withMessages([
                'file' => 'Failed to store uploaded file.',
            ]);
        }

        // Read image dimensions from the original
        $width = null;
        $height = null;
        $thumbnailPath = null;
        $mediumPath = null;

        $mime = $file->getMimeType();
        $canGenerateVariants = $this->canGenerateVariants($mime);

        if ($canGenerateVariants) {
            [$width, $height] = $this->readDimensions($disk, $originalPath);
            $thumbnailPath = $this->generateVariant($disk, $originalPath, $basePath, $uniqueName, self::THUMBNAIL_WIDTH, 'thumb');
            $mediumPath = $this->generateVariant($disk, $originalPath, $basePath, $uniqueName, self::MEDIUM_WIDTH, 'medium');
        }

        // Create the database record
        $media = Media::create([
            'uploaded_by'       => $uploader->id,
            'collection'        => $collection,
            'original_filename' => $file->getClientOriginalName(),
            'disk'              => $disk,
            'path'              => $originalPath,
            'thumbnail_path'    => $thumbnailPath,
            'medium_path'       => $mediumPath,
            'mime_type'         => $mime,
            'size'              => $file->getSize(),
            'width'             => $width,
            'height'            => $height,
            'alt_text'          => $options['alt_text'] ?? null,
            'mediable_type'     => $options['mediable_type'] ?? null,
            'mediable_id'       => $options['mediable_id'] ?? null,
            'meta'              => $options['meta'] ?? null,
        ]);

        return $media;
    }

    /* ------------------------------------------
     | VALIDATION
     ------------------------------------------ */

    protected function validate(UploadedFile $file, string $collection): void
    {
        if (! in_array($collection, Media::COLLECTIONS, true)) {
            throw ValidationException::withMessages([
                'collection' => "Invalid collection: {$collection}",
            ]);
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, Media::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => "File type not allowed: {$mime}. Allowed: JPEG, PNG, WebP, GIF, SVG.",
            ]);
        }

        if ($file->getSize() > Media::MAX_SIZE_BYTES) {
            $maxMb = Media::MAX_SIZE_BYTES / 1_048_576;
            throw ValidationException::withMessages([
                'file' => "File exceeds maximum size of {$maxMb} MB.",
            ]);
        }
    }

    /* ------------------------------------------
     | PATH & NAMING
     ------------------------------------------ */

    /**
     * Build a storage path like: media/avatars/42/ or media/site_assets/
     */
    protected function buildBasePath(string $collection, int $userId): string
    {
        $collectionFolder = match ($collection) {
            Media::COLLECTION_AVATAR          => 'avatars',
            Media::COLLECTION_SQUADRON_EMBLEM => 'squadron_emblems',
            Media::COLLECTION_OPERATION_IMAGE => 'operation_images',
            Media::COLLECTION_SHIP_IMAGE      => 'ship_images',
            Media::COLLECTION_SITE_ASSET      => 'site_assets',
            default                           => 'misc',
        };

        // User-scoped collections get a user subfolder
        $userScoped = [
            Media::COLLECTION_AVATAR,
        ];

        if (in_array($collection, $userScoped, true)) {
            return "media/{$collectionFolder}/{$userId}";
        }

        return "media/{$collectionFolder}";
    }

    /**
     * Generate a unique, safe filename preserving the original extension.
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);

        return "{$timestamp}_{$random}.{$extension}";
    }

    /* ------------------------------------------
     | VARIANT GENERATION
     ------------------------------------------ */

    /**
     * Only raster images get variants. SVGs and animated formats are
     * served as-is (resizing SVGs is pointless; animated GIF/WebP
     * resizing requires specialized tooling and can lose frames).
     */
    protected function canGenerateVariants(string $mime): bool
    {
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/webp',
        ], true);
    }

    /**
     * Read width and height from a stored image.
     */
    protected function readDimensions(string $disk, string $path): array
    {
        $fullPath = Storage::disk($disk)->path($path);
        $info = @getimagesize($fullPath);

        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /**
     * Generate a resized variant of the original image.
     * Returns the storage path of the variant, or null on failure.
     */
    protected function generateVariant(
        string $disk,
        string $originalPath,
        string $basePath,
        string $originalFilename,
        int $maxWidth,
        string $suffix
    ): ?string {
        $fullPath = Storage::disk($disk)->path($originalPath);

        // Don't upscale: if original is smaller than target, skip this variant
        $info = @getimagesize($fullPath);
        if ($info && $info[0] <= $maxWidth) {
            return null;
        }

        try {
            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($fullPath);

            // Scale down maintaining aspect ratio
            $image->scaleDown(width: $maxWidth);

            // Build variant filename: 20260206_abc123_thumb.jpg
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $nameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
            $variantFilename = "{$nameWithoutExt}_{$suffix}.{$extension}";
            $variantPath = "{$basePath}/{$variantFilename}";

            $variantFullPath = Storage::disk($disk)->path($variantPath);

            // Ensure directory exists
            $dir = dirname($variantFullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $image->save($variantFullPath, quality: 80);

            return $variantPath;
        } catch (\Throwable $e) {
            // Variant generation is non-critical. Log and continue.
            report($e);
            return null;
        }
    }
}
