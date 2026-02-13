<?php

namespace App\Domain\Media;

use App\Models\Media;
use App\Models\User;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Actions\DeleteMedia;
use App\Domain\Media\Actions\AttachMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class MediaService
{
    /**
     * Upload a file and create a Media record.
     */
    public function upload(
        UploadedFile $file,
        User $uploader,
        string $collection,
        array $options = []
    ): Media {
        return (new UploadMedia())->execute($file, $uploader, $collection, $options);
    }

    /**
     * Upload and immediately attach to an entity.
     */
    public function uploadAndAttach(
        UploadedFile $file,
        User $uploader,
        string $collection,
        Model $entity,
        array $options = []
    ): Media {
        $media = $this->upload($file, $uploader, $collection, $options);

        return $this->attach($media, $entity);
    }

    /**
     * Attach an existing media record to an entity.
     */
    public function attach(Media $media, Model $entity, bool $replacePrevious = true): Media
    {
        return (new AttachMedia())->execute($media, $entity, $replacePrevious);
    }

    /**
     * Delete a media record and its files from disk.
     */
    public function delete(Media $media): void
    {
        (new DeleteMedia())->execute($media);
    }

    /**
     * Replace the current media on a single-attachment entity.
     * Uploads the new file, attaches it, and deletes the old one.
     */
    public function replace(
        UploadedFile $file,
        User $uploader,
        string $collection,
        Model $entity,
        array $options = []
    ): Media {
        // Find the old media (if any)
        $oldMedia = Media::where('mediable_type', get_class($entity))
            ->where('mediable_id', $entity->id)
            ->where('collection', $collection)
            ->first();

        // Upload and attach the new one
        $newMedia = $this->uploadAndAttach($file, $uploader, $collection, $entity, $options);

        // Delete the old one (files + record)
        if ($oldMedia) {
            $this->delete($oldMedia);
        }

        return $newMedia;
    }

    /**
     * List media with optional filters.
     */
    public function list(array $filters = [], int $perPage = 20)
    {
        $query = Media::query()
            ->with([
                'uploader:id,rsi_handle,discord_name,rank,rank_level',
                'uploader.roles:id,slug,name',
            ])
            ->orderByDesc('created_at');

        if (! empty($filters['collection'])) {
            $query->inCollection($filters['collection']);
        }

        if (! empty($filters['uploaded_by'])) {
            $query->uploadedBy($filters['uploaded_by']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['mime_type'])) {
            $query->where('mime_type', $filters['mime_type']);
        }

        if (! empty($filters['unattached']) && $filters['unattached'] === true) {
            $query->unattached();
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
