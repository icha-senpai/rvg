<?php

namespace App\Http\Inertia;

use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ArchiveNavigationShareService
{
    public function forUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $categories = ArchiveCategory::query()
            ->where(function (Builder $query) use ($user) {
                $query->whereHas('topics', function (Builder $topicQuery) use ($user) {
                    $topicQuery->published()->visibleTo($user);
                })->orWhereHas('directEntries', function (Builder $entryQuery) use ($user) {
                    $entryQuery->published()->visibleTo($user);
                });
            })
            ->with([
                'topics' => function ($query) use ($user) {
                    $query->published()
                        ->visibleTo($user)
                        ->with([
                            'category:id,name,slug,sort_order',
                            'entries' => function ($entryQuery) use ($user) {
                                $entryQuery->published()
                                    ->visibleTo($user)
                                    ->orderBy('sort_order')
                                    ->orderBy('title');
                            },
                        ])
                        ->withCount([
                            'entries as visible_entries_count' => function (Builder $entryQuery) use ($user) {
                                $entryQuery->published()->visibleTo($user);
                            },
                        ])
                        ->orderBy('sort_order')
                        ->orderBy('title');
                },
                'directEntries' => function ($query) use ($user) {
                    $query->published()
                        ->visibleTo($user)
                        ->with(['categories:id,name,slug,sort_order'])
                        ->orderBy('archive_entries.sort_order')
                        ->orderBy('archive_entries.title');
                },
            ])
            ->withCount([
                'topics as visible_topics_count' => function (Builder $query) use ($user) {
                    $query->published()->visibleTo($user);
                },
                'directEntries as visible_direct_entries_count' => function (Builder $query) use ($user) {
                    $query->published()->visibleTo($user);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories->map(function (ArchiveCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'sort_order' => $category->sort_order,
                    'href' => route('archive.category', $category),
                    'visible_topics_count' => (int) ($category->visible_topics_count ?? $category->topics->count()),
                    'visible_direct_entries_count' => (int) ($category->visible_direct_entries_count ?? $category->directEntries->count()),
                    'visible_entries_count' => (int) $category->topics->sum(fn (ArchiveTopic $topic) => (int) ($topic->visible_entries_count ?? $topic->entries->count())) + (int) ($category->visible_direct_entries_count ?? $category->directEntries->count()),
                    'entries' => $category->directEntries->map(fn (ArchiveEntry $entry) => [
                        'id' => $entry->id,
                        'title' => $entry->title,
                        'slug' => $entry->slug,
                        'href' => route('archive.category.entry', [
                            'category' => $category,
                            'entry' => $entry,
                        ]),
                    ])->values(),
                    'topics' => $category->topics->map(function (ArchiveTopic $topic) {
                        return [
                            'id' => $topic->id,
                            'title' => $topic->title,
                            'slug' => $topic->slug,
                            'minimum_rank_label' => $topic->minimumRankLabel(),
                            'visible_entries_count' => (int) ($topic->visible_entries_count ?? $topic->entries->count()),
                            'href' => route('archive.topic', $topic),
                            'entries' => $topic->entries->map(fn (ArchiveEntry $entry) => [
                                'id' => $entry->id,
                                'title' => $entry->title,
                                'slug' => $entry->slug,
                                'href' => route('archive.entry', [
                                    'topic' => $topic,
                                    'entry' => $entry,
                                ]),
                            ])->values(),
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }
}
