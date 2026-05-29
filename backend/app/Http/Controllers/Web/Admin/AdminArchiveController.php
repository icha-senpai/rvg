<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTopic;
use App\Models\AuthAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminArchiveController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('access-admin-panel');

        $previewRankLevel = $this->previewRankLevel($request);

        $categories = ArchiveCategory::query()
            ->with(['directEntries' => function ($query) {
                $query->orderBy('sort_order')
                    ->orderBy('title');
            }])
            ->withCount('topics')
            ->withCount(['directEntries as direct_entries_count'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ArchiveCategory $category) => $this->presentCategory($category, $previewRankLevel));

        $topics = ArchiveTopic::query()
            ->with('category:id,name,slug')
            ->withCount('entries')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (ArchiveTopic $topic) => $this->presentTopic($topic, $previewRankLevel));

        return Inertia::render('Admin/ArchiveIndex', [
            'categories' => $categories,
            'topics' => $topics,
            'categoryOptions' => $this->categoryOptions(),
            'rankOptions' => $this->rankOptions(),
            'previewRankLevel' => $previewRankLevel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $data = $this->validateTopic($request);
        $category = ArchiveCategory::query()->findOrFail($data['archive_category_id']);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['category_label'] = $category->name;
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;
        $data['published_at'] = ($data['is_published'] ?? false) ? now() : null;

        $topic = ArchiveTopic::create($data);

        $this->logArchiveAction($request, 'archive.topic.created', [
            'topic_id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'minimum_rank_level' => $topic->minimum_rank_level,
            'is_published' => $topic->is_published,
        ]);

        return redirect()
            ->route('admin.archive.index')
            ->with('success', 'Archive topic created.');
    }

    public function update(Request $request, ArchiveTopic $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $before = $topic->only([
            'archive_category_id',
            'title',
            'slug',
            'description',
            'category_label',
            'card_image_path',
            'banner_image_path',
            'sort_order',
            'minimum_rank_level',
            'is_published',
            'published_at',
        ]);

        $data = $this->validateTopic($request, $topic);
        $category = ArchiveCategory::query()->findOrFail($data['archive_category_id']);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['category_label'] = $category->name;
        $data['updated_by'] = $request->user()?->id;

        if (($data['is_published'] ?? false) && ! $topic->published_at) {
            $data['published_at'] = now();
        }

        if (! ($data['is_published'] ?? false)) {
            $data['published_at'] = null;
        }

        $topic->update($data);

        $this->logArchiveAction($request, 'archive.topic.updated', [
            'topic_id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'changed_fields' => array_keys($topic->getChanges()),
            'before' => $before,
            'after' => $topic->only(array_keys($before)),
        ]);

        return redirect()
            ->route('admin.archive.index')
            ->with('success', 'Archive topic updated.');
    }

    public function destroy(Request $request, ArchiveTopic $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $entriesCount = $topic->entries()->count();

        $snapshot = [
            'topic_id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'minimum_rank_level' => $topic->minimum_rank_level,
            'is_published' => $topic->is_published,
            'entries_count' => $entriesCount,
        ];

        $topic->entries()->delete();
        $topic->delete();

        $this->logArchiveAction($request, 'archive.topic.deleted', $snapshot);

        return redirect()
            ->route('admin.archive.index')
            ->with('success', 'Archive topic and its entries moved to trash.');
    }

    protected function validateTopic(Request $request, ?ArchiveTopic $topic = null): array
    {
        return $request->validate([
            'archive_category_id' => ['required', 'integer', 'exists:archive_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('archive_topics', 'slug')->ignore($topic?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'card_image_path' => ['nullable', 'string', 'max:2048'],
            'banner_image_path' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'minimum_rank_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'is_published' => ['boolean'],
        ]);
    }

    protected function normalizeSlug(string $value): string
    {
        return Str::slug($value);
    }

    protected function presentTopic(ArchiveTopic $topic, ?int $previewRankLevel = null): array
    {
        $topic->loadMissing('category:id,name,slug');

        return [
            'id' => $topic->id,
            'archive_category_id' => $topic->archive_category_id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'category_label' => $topic->category?->name ?? $topic->category_label,
            'card_image_path' => $topic->card_image_path,
            'banner_image_path' => $topic->banner_image_path,
            'sort_order' => $topic->sort_order,
            'minimum_rank_level' => $topic->minimum_rank_level,
            'minimum_rank_label' => $topic->minimumRankLabel(),
            'is_published' => $topic->is_published,
            'published_label' => $topic->published_at?->format('M j, Y'),
            'updated_label' => $topic->updated_at?->format('M j, Y'),
            'entries_count' => (int) ($topic->entries_count ?? 0),
            'preview_visible' => $this->isVisibleAtRank($topic, $previewRankLevel),
            'public_href' => route('archive.topic', $topic),
            'entries_admin_href' => route('admin.archive.topics.entries.index', $topic),
        ];
    }

    protected function presentCategory(ArchiveCategory $category, ?int $previewRankLevel = null): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'topics_count' => (int) ($category->topics_count ?? 0),
            'direct_entries_count' => (int) ($category->direct_entries_count ?? 0),
            'direct_entries' => $category->directEntries
                ->map(fn (ArchiveEntry $entry) => $this->presentDirectCategoryEntry($entry, $category, $previewRankLevel))
                ->values(),
            'entries_admin_href' => route('admin.archive.categories.entries.index', $category),
            'create_entry_admin_href' => route('admin.archive.categories.entries.index', [
                'category' => $category,
                'create' => 1,
            ]),
            'public_href' => route('archive.category', $category),
        ];
    }

    protected function presentDirectCategoryEntry(ArchiveEntry $entry, ArchiveCategory $category, ?int $previewRankLevel = null): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'excerpt' => $entry->excerpt,
            'sort_order' => $entry->sort_order,
            'minimum_rank_level' => $entry->minimum_rank_level,
            'minimum_rank_label' => $entry->minimumRankLabel(),
            'is_published' => $entry->is_published,
            'published_label' => $entry->published_at?->format('M j, Y'),
            'updated_label' => $entry->updated_at?->format('M j, Y'),
            'preview_visible' => $this->isVisibleAtRank($entry, $previewRankLevel),
            'public_href' => route('archive.category.entry', [$category, $entry]),
            'edit_admin_href' => route('admin.archive.categories.entries.index', [
                'category' => $category,
                'entry' => $entry->id,
            ]),
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

    protected function isVisibleAtRank(ArchiveTopic|ArchiveEntry $item, ?int $rankLevel): bool
    {
        if (! $item->is_published) {
            return false;
        }

        if ($item->published_at && $item->published_at->isFuture()) {
            return false;
        }

        if ($item->minimum_rank_level === null || $rankLevel === null) {
            return true;
        }

        return (int) $item->minimum_rank_level <= $rankLevel;
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

    protected function categoryOptions(): array
    {
        return ArchiveCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (ArchiveCategory $category) => [
                'value' => $category->id,
                'label' => $category->name,
            ])
            ->values()
            ->all();
    }
}
