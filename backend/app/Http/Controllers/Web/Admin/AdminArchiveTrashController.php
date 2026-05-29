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
use Inertia\Inertia;
use Inertia\Response;

class AdminArchiveTrashController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('access-admin-panel');

        return Inertia::render('Admin/ArchiveTrash', [
            'topics' => ArchiveTopic::onlyTrashed()
                ->withCount(['entries' => fn ($query) => $query->withTrashed()])
                ->latest('deleted_at')
                ->get()
                ->map(fn (ArchiveTopic $topic) => $this->presentTopic($topic)),
            'entries' => ArchiveEntry::onlyTrashed()
                ->whereDoesntHave('topic', fn ($query) => $query->onlyTrashed())
                ->where(function ($query) {
                    $query->whereNotNull('archive_topic_id')
                        ->orWhereDoesntHave('categories', fn ($categoryQuery) => $categoryQuery->onlyTrashed());
                })
                ->with(['topic' => fn ($query) => $query->withTrashed()])
                ->withCount(['categories' => fn ($query) => $query->withTrashed(), 'tags' => fn ($query) => $query->withTrashed()])
                ->latest('deleted_at')
                ->get()
                ->map(fn (ArchiveEntry $entry) => $this->presentEntry($entry)),
            'categories' => ArchiveCategory::onlyTrashed()
                ->withCount(['directEntries as entries_count' => fn ($query) => $query->withTrashed()])
                ->latest('deleted_at')
                ->get()
                ->map(fn (ArchiveCategory $category) => $this->presentCategory($category)),
            'tags' => ArchiveTag::onlyTrashed()
                ->withCount(['entries' => fn ($query) => $query->withTrashed()])
                ->latest('deleted_at')
                ->get()
                ->map(fn (ArchiveTag $tag) => $this->presentTag($tag)),
        ]);
    }

    public function restoreTopic(Request $request, int $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveTopic::onlyTrashed()->findOrFail($topic);
        $entriesCount = $model->entries()->onlyTrashed()->count();

        $model->restore();
        $model->entries()->onlyTrashed()->restore();

        $this->logArchiveAction($request, 'archive.topic.restored', [
            'topic_id' => $model->id,
            'title' => $model->title,
            'slug' => $model->slug,
            'entries_restored_count' => $entriesCount,
        ]);

        return back()->with('success', 'Archive topic and its entries restored.');
    }

    public function forceDeleteTopic(Request $request, int $topic): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveTopic::onlyTrashed()->withCount(['entries' => fn ($query) => $query->withTrashed()])->findOrFail($topic);
        $snapshot = $this->presentTopic($model);
        $entriesCount = $model->entries()->withTrashed()->count();

        $model->entries()->withTrashed()->forceDelete();
        $model->forceDelete();

        $this->logArchiveAction($request, 'archive.topic.force_deleted', [
            ...$snapshot,
            'entries_force_deleted_count' => $entriesCount,
        ]);

        return back()->with('success', 'Archive topic and its entries permanently deleted.');
    }

    public function restoreEntry(Request $request, int $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveEntry::onlyTrashed()->findOrFail($entry);
        $model->restore();

        $this->logArchiveAction($request, 'archive.entry.restored', [
            'entry_id' => $model->id,
            'topic_id' => $model->archive_topic_id,
            'title' => $model->title,
            'slug' => $model->slug,
        ]);

        return back()->with('success', 'Archive entry restored.');
    }

    public function forceDeleteEntry(Request $request, int $entry): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveEntry::onlyTrashed()->with(['topic' => fn ($query) => $query->withTrashed()])->findOrFail($entry);
        $snapshot = $this->presentEntry($model);
        $model->forceDelete();

        $this->logArchiveAction($request, 'archive.entry.force_deleted', $snapshot);

        return back()->with('success', 'Archive entry permanently deleted.');
    }

    public function restoreCategory(Request $request, int $category): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveCategory::onlyTrashed()->findOrFail($category);
        $entriesCount = $model->directEntries()->withTrashed()->onlyTrashed()->count();

        $model->restore();
        $model->directEntries()->withTrashed()->onlyTrashed()->restore();

        $this->logArchiveAction($request, 'archive.category.restored', [
            'category_id' => $model->id,
            'name' => $model->name,
            'slug' => $model->slug,
            'entries_restored_count' => $entriesCount,
        ]);

        return back()->with('success', 'Archive category and its direct entries restored.');
    }

    public function forceDeleteCategory(Request $request, int $category): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveCategory::onlyTrashed()->withCount(['directEntries as entries_count' => fn ($query) => $query->withTrashed()])->findOrFail($category);
        $snapshot = $this->presentCategory($model);
        $entriesCount = $model->directEntries()->withTrashed()->count();

        $model->directEntries()->withTrashed()->forceDelete();
        $model->forceDelete();

        $this->logArchiveAction($request, 'archive.category.force_deleted', [
            ...$snapshot,
            'entries_force_deleted_count' => $entriesCount,
        ]);

        return back()->with('success', 'Archive category and its direct entries permanently deleted.');
    }

    public function restoreTag(Request $request, int $tag): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveTag::onlyTrashed()->findOrFail($tag);
        $model->restore();

        $this->logArchiveAction($request, 'archive.tag.restored', [
            'tag_id' => $model->id,
            'name' => $model->name,
            'slug' => $model->slug,
        ]);

        return back()->with('success', 'Archive tag restored.');
    }

    public function forceDeleteTag(Request $request, int $tag): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $model = ArchiveTag::onlyTrashed()->withCount(['entries' => fn ($query) => $query->withTrashed()])->findOrFail($tag);
        $snapshot = $this->presentTag($model);
        $model->forceDelete();

        $this->logArchiveAction($request, 'archive.tag.force_deleted', $snapshot);

        return back()->with('success', 'Archive tag permanently deleted.');
    }

    protected function presentTopic(ArchiveTopic $topic): array
    {
        return [
            'id' => $topic->id,
            'type' => 'Topic',
            'title' => $topic->title,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'deleted_label' => $topic->deleted_at?->format('M j, Y g:i A'),
            'entries_count' => (int) ($topic->entries_count ?? 0),
            'restore_href' => route('admin.archive.trash.topics.restore', $topic->id),
            'force_delete_href' => route('admin.archive.trash.topics.force-delete', $topic->id),
        ];
    }

    protected function presentEntry(ArchiveEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'type' => 'Entry',
            'title' => $entry->title,
            'slug' => $entry->slug,
            'description' => $entry->excerpt,
            'topic_title' => $entry->topic?->title,
            'deleted_label' => $entry->deleted_at?->format('M j, Y g:i A'),
            'restore_href' => route('admin.archive.trash.entries.restore', $entry->id),
            'force_delete_href' => route('admin.archive.trash.entries.force-delete', $entry->id),
        ];
    }

    protected function presentCategory(ArchiveCategory $category): array
    {
        return [
            'id' => $category->id,
            'type' => 'Category',
            'title' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'deleted_label' => $category->deleted_at?->format('M j, Y g:i A'),
            'entries_count' => (int) ($category->entries_count ?? 0),
            'restore_href' => route('admin.archive.trash.categories.restore', $category->id),
            'force_delete_href' => route('admin.archive.trash.categories.force-delete', $category->id),
        ];
    }

    protected function presentTag(ArchiveTag $tag): array
    {
        return [
            'id' => $tag->id,
            'type' => 'Tag',
            'title' => $tag->name,
            'slug' => $tag->slug,
            'description' => null,
            'deleted_label' => $tag->deleted_at?->format('M j, Y g:i A'),
            'entries_count' => (int) ($tag->entries_count ?? 0),
            'restore_href' => route('admin.archive.trash.tags.restore', $tag->id),
            'force_delete_href' => route('admin.archive.trash.tags.force-delete', $tag->id),
        ];
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
}
