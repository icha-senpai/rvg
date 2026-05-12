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

/**
 * Handles media file uploads, variant generation, and creation of the stored
 * media record.
 */
class UploadMedia
{
    protected const THUMBNAIL_WIDTH = 200;
    protected const MEDIUM_WIDTH    = 800;

    public function execute(
        UploadedFile $file,
        User $uploader,
        string $collection,
        array $options = []
    ): Media {
        $mime = $this->validateUploadMetadata($file, $collection);

        $disk = 'public';
        $basePath = $this->buildBasePath($collection, $uploader->id);
        $uniqueName = $this->generateFilename($file);

        $originalPath = $file->storeAs($basePath, $uniqueName, $disk);

        if (! $originalPath) {
            throw ValidationException::withMessages([
                'file' => 'Failed to store uploaded file.',
            ]);
        }

        $storedPath = Storage::disk($disk)->path($originalPath);

        if (! is_string($storedPath) || trim($storedPath) === '' || ! is_file($storedPath)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file was stored, but could not be read back from disk.',
            ]);
        }

        if ($this->isImageMime($mime) && @getimagesize($storedPath) === false) {
            Storage::disk($disk)->delete($originalPath);

            throw ValidationException::withMessages([
                'file' => 'Invalid image file.',
            ]);
        }

        $width = null;
        $height = null;
        $thumbnailPath = null;
        $mediumPath = null;

        if ($this->canGenerateVariants($mime)) {
            [$width, $height] = $this->readDimensions($disk, $originalPath);
            $thumbnailPath = $this->generateVariant($disk, $originalPath, $basePath, $uniqueName, self::THUMBNAIL_WIDTH, 'thumb');
            $mediumPath = $this->generateVariant($disk, $originalPath, $basePath, $uniqueName, self::MEDIUM_WIDTH, 'medium');
        }

        return Media::create([
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
    }

    protected function validateUploadMetadata(UploadedFile $file, string $collection): string
    {
        if (! in_array($collection, Media::COLLECTIONS, true)) {
            throw ValidationException::withMessages([
                'collection' => "Invalid collection: {$collection}",
            ]);
        }

        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => $file->getErrorMessage() ?: 'The uploaded file is not valid.',
            ]);
        }

        if ($file->getSize() > Media::MAX_SIZE_BYTES) {
            $maxMb = Media::MAX_SIZE_BYTES / 1_048_576;

            throw ValidationException::withMessages([
                'file' => "File exceeds maximum size of {$maxMb} MB.",
            ]);
        }

        $mimeAliases = [
            'image/jpg' => 'image/jpeg',
            'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
        ];

        $serverMime = $file->getMimeType();
        $serverMime = $serverMime ? ($mimeAliases[$serverMime] ?? $serverMime) : null;

        $clientMime = $file->getClientMimeType();
        $clientMime = $clientMime ? ($mimeAliases[$clientMime] ?? $clientMime) : null;

        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: ($file->guessExtension() ?: '')
        );

        $extensionToMime = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        $mime = null;

        if ($serverMime && in_array($serverMime, Media::ALLOWED_MIMES, true)) {
            $mime = $serverMime;
        } elseif ($clientMime && in_array($clientMime, Media::ALLOWED_MIMES, true)) {
            $mime = $clientMime;
        } elseif ($extension && isset($extensionToMime[$extension])) {
            $mime = $extensionToMime[$extension];
        }

        if (! $mime) {
            throw ValidationException::withMessages([
                'file' => 'File type not allowed. Allowed: JPEG, PNG, WebP, GIF.',
            ]);
        }

        return $mime;
    }

    protected function isImageMime(string $mime): bool
    {
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ], true);
    }

    protected function buildBasePath(string $collection, int $userId): string
    {
        $collectionFolder = match ($collection) {
            Media::COLLECTION_AVATAR           => 'avatars',
            Media::COLLECTION_SQUADRON_EMBLEM => 'squadron_emblems',
            Media::COLLECTION_OPERATION_IMAGE => 'operation_images',
            Media::COLLECTION_SHIP_IMAGE      => 'ship_images',
            Media::COLLECTION_SITE_ASSET      => 'site_assets',
            default                           => 'misc',
        };

        $userScoped = [
            Media::COLLECTION_AVATAR,
        ];

        if (in_array($collection, $userScoped, true)) {
            return "media/{$collectionFolder}/{$userId}";
        }

        return "media/{$collectionFolder}";
    }

    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();

        if (! $extension) {
            throw ValidationException::withMessages([
                'file' => 'Uploaded file is missing a valid extension.',
            ]);
        }

        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);

        return "{$timestamp}_{$random}.{$extension}";
    }

    protected function canGenerateVariants(string $mime): bool
    {
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/webp',
        ], true);
    }

    protected function readDimensions(string $disk, string $path): array
    {
        $fullPath = Storage::disk($disk)->path($path);

        if (! is_string($fullPath) || trim($fullPath) === '' || ! is_file($fullPath)) {
            return [null, null];
        }

        $info = @getimagesize($fullPath);

        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    protected function generateVariant(
        string $disk,
        string $originalPath,
        string $basePath,
        string $originalFilename,
        int $maxWidth,
        string $suffix
    ): ?string {
        $fullPath = Storage::disk($disk)->path($originalPath);

        if (! is_string($fullPath) || trim($fullPath) === '' || ! is_file($fullPath)) {
            return null;
        }

        $info = @getimagesize($fullPath);
        if ($info && $info[0] <= $maxWidth) {
            return null;
        }

        try {
            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($fullPath);

            $image->scaleDown(width: $maxWidth);

            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $nameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
            $variantFilename = "{$nameWithoutExt}_{$suffix}.{$extension}";
            $variantPath = "{$basePath}/{$variantFilename}";

            $variantFullPath = Storage::disk($disk)->path($variantPath);

            $dir = dirname($variantFullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $image->save($variantFullPath, quality: 80);

            return $variantPath;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}