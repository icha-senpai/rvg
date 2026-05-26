<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTag;
use App\Models\ArchiveTopic;
use App\Models\AuthAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminArchiveEntryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, ArchiveTopic $topic): Response
    {
        $this->authorize('access-admin-panel');

        $previewRankLevel = $this->previewRankLevel($request);

        $entries = $topic->entries()
            ->with(['categories:id,name,slug', 'tags:id,name,slug'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (ArchiveEntry $entry) => $this->presentEntry($entry, $topic, $previewRankLevel));

        return Inertia::render('Admin/ArchiveEntries', [
            'topic' => $this->presentTopic($topic, $previewRankLevel),
            'entries' => $entries,
            'categoryOptions' => $this->categoryOptions(),
            'tagOptions' => $this->tagOptions(),
            'rankOptions' => $this->rankOptions(),
            'previewRankLevel' => $previewRankLevel,
        ]);
    }

    public function store(Request $request, ArchiveTopic $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $data = $this->validateEntry($request, $topic);
        $categoryIds = Arr::pull($data, 'category_ids', []);
        $tagIds = Arr::pull($data, 'tag_ids', []);

        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['archive_topic_id'] = $topic->id;
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;
        $data['published_at'] = ($data['is_published'] ?? false) ? now() : null;

        $entry = ArchiveEntry::create($data);
        $entry->categories()->sync($categoryIds);
        $entry->tags()->sync($tagIds);

        $this->logArchiveAction($request, 'archive.entry.created', [
            'topic_id' => $topic->id,
            'topic_title' => $topic->title,
            'entry_id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'minimum_rank_level' => $entry->minimum_rank_level,
            'is_published' => $entry->is_published,
            'category_ids' => array_values($categoryIds),
            'tag_ids' => array_values($tagIds),
        ]);

        return redirect()
            ->route('admin.archive.topics.entries.index', $topic)
            ->with('success', 'Archive entry created.');
    }

    public function update(Request $request, ArchiveTopic $topic, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');
        $this->assertEntryBelongsToTopic($topic, $entry);

        $entry->loadMissing(['categories:id', 'tags:id']);

        $before = $entry->only([
            'title',
            'slug',
            'excerpt',
            'body',
            'banner_image_path',
            'sort_order',
            'minimum_rank_level',
            'is_published',
            'published_at',
        ]);
        $beforeCategoryIds = $entry->categories->pluck('id')->values()->all();
        $beforeTagIds = $entry->tags->pluck('id')->values()->all();

        $data = $this->validateEntry($request, $topic, $entry);
        $categoryIds = Arr::pull($data, 'category_ids', []);
        $tagIds = Arr::pull($data, 'tag_ids', []);

        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['updated_by'] = $request->user()?->id;

        if (($data['is_published'] ?? false) && ! $entry->published_at) {
            $data['published_at'] = now();
        }

        if (! ($data['is_published'] ?? false)) {
            $data['published_at'] = null;
        }

        $entry->update($data);
        $entry->categories()->sync($categoryIds);
        $entry->tags()->sync($tagIds);

        $this->logArchiveAction($request, 'archive.entry.updated', [
            'topic_id' => $topic->id,
            'topic_title' => $topic->title,
            'entry_id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'changed_fields' => array_keys($entry->getChanges()),
            'before' => array_merge($before, [
                'category_ids' => $beforeCategoryIds,
                'tag_ids' => $beforeTagIds,
            ]),
            'after' => array_merge($entry->only(array_keys($before)), [
                'category_ids' => array_values($categoryIds),
                'tag_ids' => array_values($tagIds),
            ]),
        ]);

        return redirect()
            ->route('admin.archive.topics.entries.index', $topic)
            ->with('success', 'Archive entry updated.');
    }

    public function destroy(Request $request, ArchiveTopic $topic, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');
        $this->assertEntryBelongsToTopic($topic, $entry);

        $entry->loadMissing(['categories:id', 'tags:id']);

        $snapshot = [
            'topic_id' => $topic->id,
            'topic_title' => $topic->title,
            'entry_id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'minimum_rank_level' => $entry->minimum_rank_level,
            'is_published' => $entry->is_published,
            'category_ids' => $entry->categories->pluck('id')->values()->all(),
            'tag_ids' => $entry->tags->pluck('id')->values()->all(),
        ];

        $entry->delete();

        $this->logArchiveAction($request, 'archive.entry.deleted', $snapshot);

        return redirect()
            ->route('admin.archive.topics.entries.index', $topic)
            ->with('success', 'Archive entry deleted.');
    }

    protected function validateEntry(Request $request, ArchiveTopic $topic, ?ArchiveEntry $entry = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('archive_entries', 'slug')
                    ->where(fn ($query) => $query->where('archive_topic_id', $topic->id))
                    ->ignore($entry?->id),
            ],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'banner_image_path' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'minimum_rank_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'is_published' => ['boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:archive_categories,id'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'exists:archive_tags,id'],
        ]);
    }

    protected function assertEntryBelongsToTopic(ArchiveTopic $topic, ArchiveEntry $entry): void
    {
        abort_unless((int) $entry->archive_topic_id === (int) $topic->id, 404);
    }

    protected function normalizeSlug(string $value): string
    {
        return Str::slug($value);
    }

    protected function presentTopic(ArchiveTopic $topic, ?int $previewRankLevel = null): array
    {
        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'category_label' => $topic->category_label,
            'minimum_rank_level' => $topic->minimum_rank_level,
            'minimum_rank_label' => $topic->minimumRankLabel(),
            'is_published' => $topic->is_published,
            'preview_visible' => $this->topicVisibleAtRank($topic, $previewRankLevel),
            'public_href' => route('archive.topic', $topic),
            'admin_href' => route('admin.archive.index'),
        ];
    }

    protected function presentEntry(ArchiveEntry $entry, ArchiveTopic $topic, ?int $previewRankLevel = null): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'excerpt' => $entry->excerpt,
            'body' => $entry->body,
            'banner_image_path' => $entry->banner_image_path,
            'sort_order' => $entry->sort_order,
            'minimum_rank_level' => $entry->minimum_rank_level,
            'minimum_rank_label' => $entry->minimumRankLabel(),
            'is_published' => $entry->is_published,
            'published_label' => $entry->published_at?->format('M j, Y'),
            'updated_label' => $entry->updated_at?->format('M j, Y'),
            'preview_visible' => $this->entryVisibleAtRank($entry, $topic, $previewRankLevel),
            'category_ids' => $entry->categories->pluck('id')->values(),
            'tag_ids' => $entry->tags->pluck('id')->values(),
            'categories' => $entry->categories->map(fn (ArchiveCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values(),
            'tags' => $entry->tags->map(fn (ArchiveTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values(),
            'public_href' => route('archive.entry', [$entry->topic, $entry]),
        ];
    }

    protected function previewRankLevel(Request $request): ?int
    {
        $value = $request->query('preview_rank_level');

        if ($value === null || $value === '') {
            return null;
        }

        $rankLevel = (int) $value;

        return $rankLevel >= 1 && $rankLevel <= 6 ? $rankLevel : null;
    }

    protected function topicVisibleAtRank(ArchiveTopic $topic, ?int $rankLevel): bool
    {
        if (! $topic->is_published) {
            return false;
        }

        if ($topic->published_at && $topic->published_at->isFuture()) {
            return false;
        }

        if ($topic->minimum_rank_level === null || $rankLevel === null) {
            return true;
        }

        return (int) $topic->minimum_rank_level <= $rankLevel;
    }

    protected function entryVisibleAtRank(ArchiveEntry $entry, ArchiveTopic $topic, ?int $rankLevel): bool
    {
        if (! $this->topicVisibleAtRank($topic, $rankLevel)) {
            return false;
        }

        if (! $entry->is_published) {
            return false;
        }

        if ($entry->published_at && $entry->published_at->isFuture()) {
            return false;
        }

        if ($entry->minimum_rank_level === null || $rankLevel === null) {
            return true;
        }

        return (int) $entry->minimum_rank_level <= $rankLevel;
    }

    protected function logArchiveAction(Request $request, string $action, array $meta): void
    {
        AuthAuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => $meta,
        ]);
    }

    protected function categoryOptions(): array
    {
        return ArchiveCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (ArchiveCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    protected function tagOptions(): array
    {
        return ArchiveTag::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (ArchiveTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])
            ->values()
            ->all();
    }

    protected function rankOptions(): array
    {
        return [
            ['value' => null, 'label' => 'Inherit topic visibility'],
            ['value' => 1, 'label' => 'Member'],
            ['value' => 2, 'label' => 'Lieutenant'],
            ['value' => 3, 'label' => 'Commander'],
            ['value' => 4, 'label' => 'Wing Commander'],
            ['value' => 5, 'label' => 'Admiral'],
            ['value' => 6, 'label' => 'Grand Admiral'],
        ];
    }
}
