<?php

namespace App\Services;

use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTag;
use App\Models\ArchiveTopic;

class AdminArchiveStatsService
{
    public function build(): array
    {
        $deletedTopics = ArchiveTopic::onlyTrashed()->count();
        $deletedEntries = ArchiveEntry::onlyTrashed()->count();
        $deletedCategories = ArchiveCategory::onlyTrashed()->count();
        $deletedTags = ArchiveTag::onlyTrashed()->count();

        return [
            'topics' => ArchiveTopic::query()->count(),
            'entries' => ArchiveEntry::query()->count(),
            'categories' => ArchiveCategory::query()->count(),
            'tags' => ArchiveTag::query()->count(),
            'trash_total' => $deletedTopics + $deletedEntries + $deletedCategories + $deletedTags,
            'trash' => [
                'topics' => $deletedTopics,
                'entries' => $deletedEntries,
                'categories' => $deletedCategories,
                'tags' => $deletedTags,
            ],
        ];
    }
}
