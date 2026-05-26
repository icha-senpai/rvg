<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('access-admin-panel');

        $topics = ArchiveTopic::query()
            ->withCount('entries')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (ArchiveTopic $topic) => $this->presentTopic($topic));

        return Inertia::render('Admin/ArchiveIndex', [
            'topics' => $topics,
            'rankOptions' => $this->rankOptions(),
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

        ArchiveTopic::create($data);

        return redirect()
            ->route('admin.archive.index')
            ->with('success', 'Archive topic created.');
    }

    public function update(Request $request, ArchiveTopic $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

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

        return redirect()
            ->route('admin.archive.index')
            ->with('success', 'Archive topic updated.');
    }

    public function destroy(ArchiveTopic $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $topic->delete();

        return redirect()
            ->route('admin.archive.index')
            ->with('success', 'Archive topic deleted.');
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

    protected function presentTopic(ArchiveTopic $topic): array
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
            'public_href' => route('archive.topic', $topic),
        ];
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
