<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTag;
use App\Models\AuthAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminArchiveCategoryEntryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, ArchiveCategory $category): Response
    {
        $this->authorize('access-admin-panel');

        $previewRankLevel = $this->previewRankLevel($request);

        $entries = $category->entries()
            ->whereNull('archive_entries.archive_topic_id')
            ->with(['tags:id,name,slug'])
            ->orderBy('archive_entries.sort_order')
            ->orderBy('archive_entries.title')
            ->get()
            ->map(fn (ArchiveEntry $entry) => $this->presentEntry($entry, $category, $previewRankLevel));

        return Inertia::render('Admin/ArchiveCategoryEntries', [
            'category' => $this->presentCategory($category, $previewRankLevel),
            'entries' => $entries,
            'tagOptions' => $this->tagOptions(),
            'rankOptions' => $this->rankOptions(),
            'previewRankLevel' => $previewRankLevel,
        ]);
    }

    public function store(Request $request, ArchiveCategory $category): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $data = $this->validateEntry($request, $category);
        $tagIds = Arr::pull($data, 'tag_ids', []);

        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['archive_topic_id'] = null;
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;
        $data['published_at'] = ($data['is_published'] ?? false) ? now() : null;

        $entry = ArchiveEntry::create($data);
        $entry->categories()->sync([$category->id]);
        $entry->tags()->sync($tagIds);

        $this->logArchiveAction($request, 'archive.category_entry.created', [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'entry_id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'minimum_rank_level' => $entry->minimum_rank_level,
            'is_published' => $entry->is_published,
            'tag_ids' => array_values($tagIds),
        ]);

        return redirect()
            ->route('admin.archive.categories.entries.index', $category)
            ->with('success', 'Archive category entry created.');
    }

    public function update(Request $request, ArchiveCategory $category, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');
        $this->assertEntryBelongsToCategory($category, $entry);

        $entry->loadMissing(['tags:id']);

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
        $beforeTagIds = $entry->tags->pluck('id')->values()->all();

        $data = $this->validateEntry($request, $category, $entry);
        $tagIds = Arr::pull($data, 'tag_ids', []);

        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['archive_topic_id'] = null;
        $data['updated_by'] = $request->user()?->id;

        if (($data['is_published'] ?? false) && ! $entry->published_at) {
            $data['published_at'] = now();
        }

        if (! ($data['is_published'] ?? false)) {
            $data['published_at'] = null;
        }

        $entry->update($data);
        $entry->categories()->sync([$category->id]);
        $entry->tags()->sync($tagIds);

        $this->logArchiveAction($request, 'archive.category_entry.updated', [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'entry_id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'changed_fields' => array_keys($entry->getChanges()),
            'before' => array_merge($before, [
                'tag_ids' => $beforeTagIds,
            ]),
            'after' => array_merge($entry->only(array_keys($before)), [
                'tag_ids' => array_values($tagIds),
            ]),
        ]);

        return redirect()
            ->route('admin.archive.categories.entries.index', $category)
            ->with('success', 'Archive category entry updated.');
    }

    public function destroy(Request $request, ArchiveCategory $category, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');
        $this->assertEntryBelongsToCategory($category, $entry);

        $entry->loadMissing(['tags:id']);

        $snapshot = [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'entry_id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'minimum_rank_level' => $entry->minimum_rank_level,
            'is_published' => $entry->is_published,
            'tag_ids' => $entry->tags->pluck('id')->values()->all(),
        ];

        $entry->delete();

        $this->logArchiveAction($request, 'archive.category_entry.deleted', $snapshot);

        return redirect()
            ->route('admin.archive.categories.entries.index', $category)
            ->with('success', 'Archive category entry deleted.');
    }

    protected function validateEntry(Request $request, ArchiveCategory $category, ?ArchiveEntry $entry = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('archive_entries', 'slug')
                    ->where(fn ($query) => $query
                        ->whereNull('archive_topic_id')
                        ->whereExists(function ($subQuery) use ($category) {
                            $subQuery->selectRaw('1')
                                ->from('archive_category_entry')
                                ->whereColumn('archive_category_entry.archive_entry_id', 'archive_entries.id')
                                ->where('archive_category_entry.archive_category_id', $category->id);
                        }))
                    ->ignore($entry?->id),
            ],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'banner_image_path' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'minimum_rank_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'is_published' => ['boolean'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'exists:archive_tags,id'],
        ]);
    }

    protected function assertEntryBelongsToCategory(ArchiveCategory $category, ArchiveEntry $entry): void
    {
        abort_unless(
            $entry->archive_topic_id === null
            && $entry->categories()->whereKey($category->id)->exists(),
            404
        );
    }

    protected function normalizeSlug(string $value): string
    {
        return Str::slug($value);
    }

    protected function presentCategory(ArchiveCategory $category, ?int $previewRankLevel = null): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'minimum_rank_label' => 'All verified members',
            'preview_visible' => true,
            'public_href' => route('archive.category', $category),
            'admin_href' => route('admin.archive.taxonomy.index'),
        ];
    }

    protected function presentEntry(ArchiveEntry $entry, ArchiveCategory $category, ?int $previewRankLevel = null): array
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
            'preview_visible' => $this->entryVisibleAtRank($entry, $previewRankLevel),
            'tag_ids' => $entry->tags->pluck('id')->values(),
            'tags' => $entry->tags->map(fn (ArchiveTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values(),
            'public_href' => route('archive.category.entry', [$category, $entry]),
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

    protected function entryVisibleAtRank(ArchiveEntry $entry, ?int $rankLevel): bool
    {
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
            ['value' => null, 'label' => 'All verified members'],
            ['value' => 1, 'label' => 'Member'],
            ['value' => 2, 'label' => 'Lieutenant'],
            ['value' => 3, 'label' => 'Commander'],
            ['value' => 4, 'label' => 'Wing Commander'],
            ['value' => 5, 'label' => 'Admiral'],
            ['value' => 6, 'label' => 'Grand Admiral'],
        ];
    }
}
