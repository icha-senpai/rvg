<?php

namespace App\Domain\Media\Actions;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class DeleteMedia
{
    /**
     * Delete a media record and all its stored files.
     */
    public function execute(Media $media): void
    {
        $disk = $media->disk;

        // Delete all file variants from disk
        $paths = array_filter([
            $media->path,
            $media->thumbnail_path,
            $media->medium_path,
        ]);

        foreach ($paths as $path) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        // Delete the database record
        $media->delete();
    }
}
