<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTag;
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
        $sort = $this->normalizeSort((string) $request->query('sort', 'recent'), ['recent', 'title', 'oldest'], 'recent');

        $categories = ArchiveCategory::query()
            ->where(function (Builder $query) use ($user) {
                $query->whereHas('topics', function (Builder $topicQuery) use ($user) {
                    $topicQuery->published()->visibleTo($user);
                })->orWhereHas('directEntries', function (Builder $entryQuery) use ($user) {
                    $entryQuery->published()->visibleTo($user);
                });
            })
            ->withCount([
                'topics as visible_topics_count' => function (Builder $query) use ($user) {
                    $query->published()->visibleTo($user);
                },
                'directEntries as visible_direct_entries_count' => function (Builder $query) use ($user) {
                    $query->published()->visibleTo($user);
                },
            ])
            ->with([
                'topics' => function ($query) use ($user) {
                    $query->published()
                        ->visibleTo($user)
                        ->with('category:id,name,slug')
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
                        ->with([
                            'categories:id,name,slug,description,sort_order',
                            'tags:id,name,slug',
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ArchiveCategory $category) => $this->presentCategoryCard($category));

        $entries = collect();

        if ($search !== '') {
            $entriesQuery = ArchiveEntry::query()
                ->published()
                ->visibleTo($user)
                ->where(function (Builder $query) use ($user) {
                    $query->whereHas('topic', function (Builder $topicQuery) use ($user) {
                        $topicQuery->published()->visibleTo($user);
                    })->orWhere(function (Builder $directQuery) {
                        $directQuery->whereNull('archive_topic_id')
                            ->whereHas('categories');
                    });
                })
                ->with([
                    'topic:id,title,slug,minimum_rank_level',
                    'categories:id,name,slug,description,sort_order',
                    'tags:id,name,slug',
                ]);

            $this->applyEntrySearch($entriesQuery, $search);
            $this->applyEntrySort($entriesQuery, $sort);

            $entries = $entriesQuery
                ->limit(12)
                ->get()
                ->map(fn (ArchiveEntry $entry) => $this->presentEntryCard($entry));
        }

        return Inertia::render('Archive/Index', [
            'categories' => $categories,
            'entries' => $entries,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
            ],
        ]);
    }

    public function category(Request $request, ArchiveCategory $category): Response
    {
        $user = $request->user()->loadMissing('roles:id,slug');

        $category->load([
            'topics' => function ($query) use ($user) {
                $query->published()
                    ->visibleTo($user)
                    ->with('category:id,name,slug')
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
                    ->with([
                        'categories:id,name,slug,description,sort_order',
                        'tags:id,name,slug',
                    ]);
            },
        ]);

        return Inertia::render('Archive/Category', [
            'category' => $this->presentCategory($category),
            'topics' => $category->topics->map(fn (ArchiveTopic $topic) => $this->presentTopicCard($topic))->values(),
            'entries' => $category->directEntries->map(fn (ArchiveEntry $entry) => $this->presentEntryCard($entry))->values(),
        ]);
    }

    public function topic(Request $request, ArchiveTopic $topic): Response
    {
        $user = $request->user()->loadMissing('roles:id,slug');

        abort_unless($this->topicIsVisibleTo($topic, $user), 404);

        $search = trim((string) $request->query('search', ''));
        $sort = $this->normalizeSort((string) $request->query('sort', 'default'), ['default', 'title', 'recent', 'oldest'], 'default');
        $tag = trim((string) $request->query('tag', ''));

        $entriesQuery = $topic->entries()
            ->published()
            ->visibleTo($user)
            ->with([
                'topic:id,title,slug,minimum_rank_level',
                'tags:id,name,slug',
            ]);

        if ($search !== '') {
            $this->applyEntrySearch($entriesQuery, $search);
        }

        if ($tag !== '') {
            $entriesQuery->whereHas('tags', function (Builder $query) use ($tag) {
                $query->where('slug', $tag);
            });
        }

        $this->applyEntrySort($entriesQuery, $sort);

        return Inertia::render('Archive/Topic', [
            'topic' => $this->presentTopic($topic),
            'entries' => $entriesQuery->get()->map(fn (ArchiveEntry $entry) => $this->presentEntryCard($entry)),
            'tagOptions' => $this->topicTagOptions($topic, $user),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'tag' => $tag,
            ],
        ]);
    }

    public function categoryEntry(Request $request, ArchiveCategory $category, ArchiveEntry $entry): Response
    {
        $user = $request->user()->loadMissing('roles:id,slug');

        $entry->loadMissing([
            'categories:id,name,slug,description,sort_order',
            'tags:id,name,slug',
        ]);

        abort_unless($entry->isDirectCategoryEntry(), 404);
        abort_unless($this->directEntryBelongsToCategory($entry, $category), 404);
        abort_unless($this->entryIsVisibleTo($entry, $user), 404);

        $relatedEntries = $category->directEntries()
            ->published()
            ->visibleTo($user)
            ->whereKeyNot($entry->id)
            ->with([
                'categories:id,name,slug,description,sort_order',
                'tags:id,name,slug',
            ])
            ->orderBy('archive_entries.sort_order')
            ->orderBy('archive_entries.title')
            ->limit(4)
            ->get()
            ->map(fn (ArchiveEntry $relatedEntry) => $this->presentEntryCard($relatedEntry));

        return Inertia::render('Archive/Entry', [
            'category' => $this->presentCategory($category),
            'topic' => null,
            'entry' => $this->presentEntry($entry),
            'relatedEntries' => $relatedEntries,
        ]);
    }

    public function entry(Request $request, ArchiveTopic $topic, ArchiveEntry $entry): Response
    {
        $user = $request->user()->loadMissing('roles:id,slug');

        abort_unless((int) $entry->archive_topic_id === (int) $topic->id, 404);
        abort_unless($this->topicIsVisibleTo($topic, $user), 404);
        abort_unless($this->entryIsVisibleTo($entry, $user), 404);

        $entry->loadMissing([
            'topic:id,title,slug,minimum_rank_level',
            'categories:id,name,slug,description,sort_order',
            'tags:id,name,slug',
        ]);

        $relatedEntries = ArchiveEntry::query()
            ->published()
            ->visibleTo($user)
            ->where('archive_topic_id', $topic->id)
            ->whereKeyNot($entry->id)
            ->with([
                'topic:id,title,slug,minimum_rank_level',
                'categories:id,name,slug,description,sort_order',
                'tags:id,name,slug',
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(4)
            ->get()
            ->map(fn (ArchiveEntry $relatedEntry) => $this->presentEntryCard($relatedEntry));

        return Inertia::render('Archive/Entry', [
            'category' => $this->presentCategory($topic->category),
            'topic' => $this->presentTopic($topic),
            'entry' => $this->presentEntry($entry),
            'relatedEntries' => $relatedEntries,
        ]);
    }

    protected function applyEntrySearch($query, string $search): void
    {
        $needle = '%' . $this->escapeLike(mb_strtolower($search)) . '%';

        $query->where(function (Builder $nested) use ($needle) {
            $this->applyCaseInsensitiveSearchConditions($nested, ['title', 'excerpt', 'body'], $needle);

            $nested->orWhereHas('topic.category', function (Builder $categoryQuery) use ($needle) {
                $this->applyCaseInsensitiveSearchConditions($categoryQuery, ['name', 'slug', 'description'], $needle);
            });

            $nested->orWhereHas('categories', function (Builder $categoryQuery) use ($needle) {
                $this->applyCaseInsensitiveSearchConditions($categoryQuery, ['name', 'slug', 'description'], $needle);
            });

            $nested->orWhereHas('tags', function (Builder $tagQuery) use ($needle) {
                $this->applyCaseInsensitiveSearchConditions($tagQuery, ['name', 'slug'], $needle);
            });
        });
    }

    protected function applyEntrySort($query, string $sort): void
    {
        match ($sort) {
            'title' => $query->orderBy('title'),
            'recent' => $query->orderByDesc('updated_at')->orderBy('title'),
            'oldest' => $query->orderBy('updated_at')->orderBy('title'),
            default => $query->orderBy('sort_order')->orderBy('title'),
        };
    }

    protected function applyCaseInsensitiveSearch(Builder $query, array $columns, string $search): void
    {
        $needle = '%' . $this->escapeLike(mb_strtolower($search)) . '%';

        $query->where(function (Builder $nested) use ($columns, $needle) {
            $this->applyCaseInsensitiveSearchConditions($nested, $columns, $needle);
        });
    }

    protected function applyCaseInsensitiveSearchConditions(Builder $query, array $columns, string $needle): void
    {
        foreach ($columns as $column) {
            $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);
            $query->orWhereRaw("LOWER({$wrappedColumn}) LIKE ?", [$needle]);
        }
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }

    protected function normalizeSort(string $sort, array $allowed, string $default): string
    {
        return in_array($sort, $allowed, true) ? $sort : $default;
    }

    protected function topicTagOptions(ArchiveTopic $topic, $user): array
    {
        return ArchiveTag::query()
            ->whereHas('entries', function (Builder $query) use ($topic, $user) {
                $query->where('archive_topic_id', $topic->id)
                    ->published()
                    ->visibleTo($user);
            })
            ->withCount(['entries' => function (Builder $query) use ($topic, $user) {
                $query->where('archive_topic_id', $topic->id)
                    ->published()
                    ->visibleTo($user);
            }])
            ->orderBy('name')
            ->get()
            ->map(fn (ArchiveTag $tag) => [
                'name' => $tag->name,
                'slug' => $tag->slug,
                'entries_count' => (int) ($tag->entries_count ?? 0),
            ])
            ->values()
            ->all();
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

    protected function directEntryBelongsToCategory(ArchiveEntry $entry, ArchiveCategory $category): bool
    {
        return $entry->categories()->whereKey($category->id)->exists();
    }

    protected function presentCategoryCard(ArchiveCategory $category): array
    {
        $category->loadMissing([
            'topics.category:id,name,slug',
            'directEntries.categories:id,name,slug,description,sort_order',
            'directEntries.tags:id,name,slug',
        ]);

        $topics = $category->topics->map(fn (ArchiveTopic $topic) => $this->presentTopicCard($topic))->values();
        $directEntries = $category->directEntries->map(fn (ArchiveEntry $entry) => $this->presentEntryCard($entry))->values();
        $topicVisibleEntriesCount = (int) $topics->sum(fn (array $topic) => $topic['visible_entries_count'] ?? 0);
        $directVisibleEntriesCount = (int) ($category->visible_direct_entries_count ?? $directEntries->count());

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'visible_topics_count' => (int) ($category->visible_topics_count ?? $topics->count()),
            'visible_direct_entries_count' => $directVisibleEntriesCount,
            'visible_entries_count' => $topicVisibleEntriesCount + $directVisibleEntriesCount,
            'topics' => $topics,
            'direct_entries' => $directEntries,
            'href' => route('archive.category', $category),
        ];
    }

    protected function presentCategory(?ArchiveCategory $category): ?array
    {
        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'href' => route('archive.category', $category),
        ];
    }

    protected function presentTopicCard(ArchiveTopic $topic): array
    {
        $topic->loadMissing('category:id,name,slug');

        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'category_label' => $topic->category?->name ?? $topic->category_label,
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
        $entry->loadMissing([
            'topic:id,title,slug,minimum_rank_level',
            'categories:id,name,slug,description,sort_order',
            'tags:id,name,slug',
        ]);

        $primaryCategory = $entry->primaryCategory();
        $href = $entry->topic
            ? route('archive.entry', [
                'topic' => $entry->topic,
                'entry' => $entry,
            ])
            : ($primaryCategory
                ? route('archive.category.entry', [
                    'category' => $primaryCategory,
                    'entry' => $entry,
                ])
                : route('archive.index'));

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
            'tags' => $entry->tags->map(fn (ArchiveTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values(),
            'categories' => $entry->categories->map(fn (ArchiveCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'href' => route('archive.category', $category),
            ])->values(),
            'topic' => $entry->topic ? [
                'title' => $entry->topic->title,
                'slug' => $entry->topic->slug,
                'href' => route('archive.topic', $entry->topic),
            ] : null,
            'category' => $primaryCategory ? [
                'name' => $primaryCategory->name,
                'slug' => $primaryCategory->slug,
                'href' => route('archive.category', $primaryCategory),
            ] : null,
            'href' => $href,
        ];
    }

    protected function presentEntry(ArchiveEntry $entry): array
    {
        return array_merge($this->presentEntryCard($entry), [
            'body' => $entry->body,
        ]);
    }
}
