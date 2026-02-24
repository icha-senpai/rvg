<?php

namespace App\Domain\Operations\Services;

use App\Domain\Media\MediaService;
use App\Models\Media;
use App\Models\Operation;

class OperationMediaService
{
    public function __construct(
        protected MediaService $media
    ) {}

    public function syncOperationImage(Operation $operation, mixed $mediaId): void
    {
        if (empty($mediaId)) {
            $this->clearOperationImage($operation);
            return;
        }

        $media = Media::find($mediaId);

        if (! $media || $media->collection !== Media::COLLECTION_OPERATION_IMAGE) {
            return;
        }

        if ($media->mediable_type !== null
            && $media->mediable_id !== null
            && ($media->mediable_type !== Operation::class || (int) $media->mediable_id !== (int) $operation->id)
        ) {
            $mediaCopy = $media->replicate(['mediable_type', 'mediable_id']);
            $mediaCopy->mediable_type = null;
            $mediaCopy->mediable_id = null;
            $mediaCopy->save();
            $media = $mediaCopy;
        }

        Media::where('mediable_type', Operation::class)
            ->where('mediable_id', $operation->id)
            ->where('collection', Media::COLLECTION_OPERATION_IMAGE)
            ->where('id', '!=', $media->id)
            ->update([
                'mediable_type' => null,
                'mediable_id'   => null,
            ]);

        $this->media->attach($media, $operation);
    }

    private function clearOperationImage(Operation $operation): void
    {
        Media::where('mediable_type', Operation::class)
            ->where('mediable_id', $operation->id)
            ->where('collection', Media::COLLECTION_OPERATION_IMAGE)
            ->update([
                'mediable_type' => null,
                'mediable_id'   => null,
            ]);
    }
}
