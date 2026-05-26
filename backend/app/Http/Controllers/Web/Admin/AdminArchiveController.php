<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
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

        $topics = ArchiveTopic::query()
            ->withCount('entries')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (ArchiveTopic $topic) => $this->presentTopic($topic, $previewRankLevel));

        return Inertia::render('Admin/ArchiveIndex', [
            'topics' => $topics,
            'rankOptions' => $this->rankOptions(),
            'previewRankLevel' => $previewRankLevel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $data = $this->validateTopic($request);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
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
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('archive_topics', 'slug')->ignore($topic?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_label' => ['nullable', 'string', 'max:100'],
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
        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'category_label' => $topic->category_label,
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

    protected function previewRankLevel(Request $request): ?int
    {
        $value = $request->query('preview_rank_level');

        if ($value === null || $value === '') {
            return null;
        }

        $rankLevel = (int) $value;

        return $rankLevel >= 1 && $rankLevel <= 6 ? $rankLevel : null;
    }

    protected function isVisibleAtRank(ArchiveTopic $topic, ?int $rankLevel): bool
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
}
