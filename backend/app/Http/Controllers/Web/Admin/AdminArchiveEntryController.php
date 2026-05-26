<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTopic;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $entries = $topic->entries()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (ArchiveEntry $entry) => $this->presentEntry($entry));

        return Inertia::render('Admin/ArchiveEntries', [
            'topic' => $this->presentTopic($topic),
            'entries' => $entries,
            'rankOptions' => $this->rankOptions(),
        ]);
    }

    public function store(Request $request, ArchiveTopic $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $data = $this->validateEntry($request, $topic);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['archive_topic_id'] = $topic->id;
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;
        $data['published_at'] = ($data['is_published'] ?? false) ? now() : null;

        ArchiveEntry::create($data);

        return redirect()
            ->route('admin.archive.topics.entries.index', $topic)
            ->with('success', 'Archive entry created.');
    }

    public function update(Request $request, ArchiveTopic $topic, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');
        $this->assertEntryBelongsToTopic($topic, $entry);

        $data = $this->validateEntry($request, $topic, $entry);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['title']);
        $data['updated_by'] = $request->user()?->id;

        if (($data['is_published'] ?? false) && ! $entry->published_at) {
            $data['published_at'] = now();
        }

        if (! ($data['is_published'] ?? false)) {
            $data['published_at'] = null;
        }

        $entry->update($data);

        return redirect()
            ->route('admin.archive.topics.entries.index', $topic)
            ->with('success', 'Archive entry updated.');
    }

    public function destroy(ArchiveTopic $topic, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');
        $this->assertEntryBelongsToTopic($topic, $entry);

        $entry->delete();

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

    protected function presentTopic(ArchiveTopic $topic): array
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
            'public_href' => route('archive.topic', $topic),
            'admin_href' => route('admin.archive.index'),
        ];
    }

    protected function presentEntry(ArchiveEntry $entry): array
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
            'public_href' => route('archive.entry', [$entry->topic, $entry]),
        ];
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
