<?php

namespace App\Domain\Squadrons;

use App\Domain\AccessControl\AccessService;
use App\Domain\Media\MediaService;
use App\Models\Media;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Handles squadron emblem uploads, permissions, and media attachment rules.
 */
class SquadronEmblemService
{
    public function __construct(
        protected AccessService $access,
        protected MediaService $media,
    ) {}

    /**
     * Store a newly uploaded emblem file path on the squadron record.
     */
    public function uploadEmblem(Squadron $squadron, UploadedFile $file): Squadron
    {
        $path = $file->store('squadrons', 'public');

        $squadron->update([
            'emblem_path' => $path,
        ]);

        return $squadron->fresh();
    }

    /**
     * Decide whether the given user is allowed to modify the squadron emblem.
     *
     * Leaders and privileged org roles always qualify, and lieutenant-level
     * squadron members may also manage emblems for their own squadron.
     */
    public function canModifyEmblem(User $user, Squadron $squadron): bool
    {
        return $this->access->isSquadronLeader($user, $squadron)
            || $this->access->isDirectorLike($user)
            || (
                $this->access->isOfficer($user)
                && $this->access->isSquadronMember($user, $squadron)
            );
    }

    /**
     * Attach an existing media item as the squadron emblem, or clear the current
     * emblem selection when null is passed.
     */
    public function selectEmblem(Squadron $squadron, ?int $emblemMediaId): Squadron
    {
        if ($emblemMediaId === null) {
            // Clearing the emblem detaches whichever emblem media record currently
            // points at this squadron without deleting the media itself.
            Media::where('mediable_type', Squadron::class)
                ->where('mediable_id', $squadron->id)
                ->where('collection', Media::COLLECTION_SQUADRON_EMBLEM)
                ->update([
                    'mediable_type' => null,
                    'mediable_id' => null,
                ]);

            return $squadron->fresh();
        }

        $media = Media::findOrFail($emblemMediaId);

        // Ensure the selected media is a valid squadron emblem.
        if ($media->collection !== Media::COLLECTION_SQUADRON_EMBLEM) {
            throw ValidationException::withMessages([
                'emblem_media_id' => 'Selected media is not a squadron emblem.',
            ]);
        }

        // Prevent one emblem asset from silently becoming shared across unrelated
        // records unless it already belongs to this squadron.
        if ($media->mediable_type && ! (
            $media->mediable_type === Squadron::class
            && (int) $media->mediable_id === (int) $squadron->id
        )) {
            throw ValidationException::withMessages([
                'emblem_media_id' => 'Selected media is already attached to another record.',
            ]);
        }

        // MediaService centralizes the actual attach-and-replace behavior so emblem
        // rules stay consistent with the broader media domain.
        $this->media->attach($media, $squadron, true);

        return $squadron->fresh();
    }
}
