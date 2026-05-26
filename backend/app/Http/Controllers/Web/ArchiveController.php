<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing('roles:id,slug');
        $search = trim((string) $request->query('search', ''));

        $topicsQuery = ArchiveTopic::query()
            ->published()
            ->visibleTo($user)
            ->withCount([
                'entries as visible_entries_count' => function (Builder $query) use ($user) {
                    $query->published()->visibleTo($user);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($search !== '') {
            $topicsQuery->where(function (Builder $query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category_label', 'like', "%{$search}%");
            });
        }

        $topics = $topicsQuery->get()->map(fn (ArchiveTopic $topic) => $this->presentTopicCard($topic));

        $entries = collect();

        if ($search !== '') {
            $entries = ArchiveEntry::query()
                ->published()
                ->visibleTo($user)
                ->whereHas('topic', function (Builder $query) use ($user) {
                    $query->published()->visibleTo($user);
                })
                ->with('topic:id,title,slug,minimum_rank_level')
                ->where(function (Builder $query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                })
                ->orderByDesc('updated_at')
                ->limit(12)
                ->get()
                ->map(fn (ArchiveEntry $entry) => $this->presentEntryCard($entry));
        }

        return Inertia::render('Archive/Index', [
            'topics' => $topics,
            'entries' => $entries,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function topic(Request $request, ArchiveTopic $topic): Response
    {
        $user = $request->user()->loadMissing('roles:id,slug');

        abort_unless($this->topicIsVisibleTo($topic, $user), 404);

        $search = trim((string) $request->query('search', ''));

        $entriesQuery = $topic->entries()
            ->published()
            ->visibleTo($user)
            ->with('topic:id,title,slug,minimum_rank_level')
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($search !== '') {
            $entriesQuery->where(function (Builder $query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Archive/Topic', [
            'topic' => $this->presentTopic($topic),
            'entries' => $entriesQuery->get()->map(fn (ArchiveEntry $entry) => $this->presentEntryCard($entry)),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function entry(Request $request, ArchiveTopic $topic, ArchiveEntry $entry): Response
    {
        $user = $request->user()->loadMissing('roles:id,slug');

        abort_unless((int) $entry->archive_topic_id === (int) $topic->id, 404);
        abort_unless($this->topicIsVisibleTo($topic, $user), 404);
        abort_unless($this->entryIsVisibleTo($entry, $user), 404);

        $entry->loadMissing('topic:id,title,slug,minimum_rank_level');

        $relatedEntries = ArchiveEntry::query()
            ->published()
            ->visibleTo($user)
            ->where('archive_topic_id', $topic->id)
            ->whereKeyNot($entry->id)
            ->with('topic:id,title,slug,minimum_rank_level')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(4)
            ->get()
            ->map(fn (ArchiveEntry $relatedEntry) => $this->presentEntryCard($relatedEntry));

        return Inertia::render('Archive/Entry', [
            'topic' => $this->presentTopic($topic),
            'entry' => $this->presentEntry($entry),
            'relatedEntries' => $relatedEntries,
        ]);
    }

    protected function topicIsVisibleTo(ArchiveTopic $topic, $user): bool
    {
        if (! $topic->is_published) {
            return false;
        }

        if ($topic->published_at && $topic->published_at->isFuture()) {
            return false;
        }

        if ($user->hasAnyRole(['director', 'tech_director'])) {
            return true;
        }

        if ($topic->minimum_rank_level === null) {
            return true;
        }

        return (int) ($user->rank_level ?? 0) >= (int) $topic->minimum_rank_level;
    }

    protected function entryIsVisibleTo(ArchiveEntry $entry, $user): bool
    {
        if (! $entry->is_published) {
            return false;
        }

        if ($entry->published_at && $entry->published_at->isFuture()) {
            return false;
        }

        if ($user->hasAnyRole(['director', 'tech_director'])) {
            return true;
        }

        if ($entry->minimum_rank_level === null) {
            return true;
        }

        return (int) ($user->rank_level ?? 0) >= (int) $entry->minimum_rank_level;
    }

    protected function presentTopicCard(ArchiveTopic $topic): array
    {
        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'category_label' => $topic->category_label,
            'card_image_path' => $topic->card_image_path,
            'banner_image_path' => $topic->banner_image_path,
            'minimum_rank_level' => $topic->minimum_rank_level,
            'minimum_rank_label' => $topic->minimumRankLabel(),
            'visible_entries_count' => (int) ($topic->visible_entries_count ?? 0),
            'href' => route('archive.topic', $topic),
        ];
    }

    protected function presentTopic(ArchiveTopic $topic): array
    {
        return array_merge($this->presentTopicCard($topic), [
            'published_at' => $topic->published_at?->toISOString(),
            'updated_at' => $topic->updated_at?->toISOString(),
            'published_label' => $topic->published_at?->format('M j, Y'),
            'updated_label' => $topic->updated_at?->format('M j, Y'),
        ]);
    }

    protected function presentEntryCard(ArchiveEntry $entry): array
    {
        $entry->loadMissing('topic:id,title,slug,minimum_rank_level');

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'excerpt' => $entry->excerpt,
            'banner_image_path' => $entry->banner_image_path,
            'minimum_rank_level' => $entry->minimum_rank_level,
            'minimum_rank_label' => $entry->minimumRankLabel(),
            'published_at' => $entry->published_at?->toISOString(),
            'updated_at' => $entry->updated_at?->toISOString(),
            'published_label' => $entry->published_at?->format('M j, Y'),
            'updated_label' => $entry->updated_at?->format('M j, Y'),
            'topic' => $entry->topic ? [
                'title' => $entry->topic->title,
                'slug' => $entry->topic->slug,
                'href' => route('archive.topic', $entry->topic),
            ] : null,
            'href' => route('archive.entry', [
                'topic' => $entry->topic?->slug ?? $entry->archive_topic_id,
                'entry' => $entry->slug,
            ]),
        ];
    }

    protected function presentEntry(ArchiveEntry $entry): array
    {
        return array_merge($this->presentEntryCard($entry), [
            'body' => $entry->body,
        ]);
    }
}
