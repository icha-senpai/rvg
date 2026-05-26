<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCategory;
use App\Models\ArchiveTag;
use App\Models\AuthAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminArchiveTaxonomyController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('access-admin-panel');

        return Inertia::render('Admin/ArchiveTaxonomy', [
            'categories' => ArchiveCategory::query()
                ->withCount('entries')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ArchiveCategory $category) => $this->presentCategory($category)),
            'tags' => ArchiveTag::query()
                ->withCount('entries')
                ->orderBy('name')
                ->get()
                ->map(fn (ArchiveTag $tag) => $this->presentTag($tag)),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $data = $this->validateCategory($request);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['name']);

        $category = ArchiveCategory::create($data);

        $this->logArchiveAction($request, 'archive.category.created', [
            'category_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'sort_order' => $category->sort_order,
        ]);

        return redirect()
            ->route('admin.archive.taxonomy.index')
            ->with('success', 'Archive category created.');
    }

    public function updateCategory(Request $request, ArchiveCategory $category): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $before = $category->only(['name', 'slug', 'description', 'sort_order']);

        $data = $this->validateCategory($request, $category);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['name']);

        $category->update($data);

        $this->logArchiveAction($request, 'archive.category.updated', [
            'category_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'changed_fields' => array_keys($category->getChanges()),
            'before' => $before,
            'after' => $category->only(array_keys($before)),
        ]);

        return redirect()
            ->route('admin.archive.taxonomy.index')
            ->with('success', 'Archive category updated.');
    }

    public function destroyCategory(Request $request, ArchiveCategory $category): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $category->loadCount('entries');

        $snapshot = [
            'category_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'entries_count' => (int) ($category->entries_count ?? 0),
        ];

        $category->delete();

        $this->logArchiveAction($request, 'archive.category.deleted', $snapshot);

        return redirect()
            ->route('admin.archive.taxonomy.index')
            ->with('success', 'Archive category deleted.');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $data = $this->validateTag($request);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['name']);

        $tag = ArchiveTag::create($data);

        $this->logArchiveAction($request, 'archive.tag.created', [
            'tag_id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ]);

        return redirect()
            ->route('admin.archive.taxonomy.index')
            ->with('success', 'Archive tag created.');
    }

    public function updateTag(Request $request, ArchiveTag $tag): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $before = $tag->only(['name', 'slug']);

        $data = $this->validateTag($request, $tag);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? $data['name']);

        $tag->update($data);

        $this->logArchiveAction($request, 'archive.tag.updated', [
            'tag_id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'changed_fields' => array_keys($tag->getChanges()),
            'before' => $before,
            'after' => $tag->only(array_keys($before)),
        ]);

        return redirect()
            ->route('admin.archive.taxonomy.index')
            ->with('success', 'Archive tag updated.');
    }

    public function destroyTag(Request $request, ArchiveTag $tag): RedirectResponse
    {
        $this->authorize('access-admin-panel');

        $tag->loadCount('entries');

        $snapshot = [
            'tag_id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'entries_count' => (int) ($tag->entries_count ?? 0),
        ];

        $tag->delete();

        $this->logArchiveAction($request, 'archive.tag.deleted', $snapshot);

        return redirect()
            ->route('admin.archive.taxonomy.index')
            ->with('success', 'Archive tag deleted.');
    }

    protected function validateCategory(Request $request, ?ArchiveCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('archive_categories', 'slug')->ignore($category?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);
    }

    protected function validateTag(Request $request, ?ArchiveTag $tag = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('archive_tags', 'slug')->ignore($tag?->id),
            ],
        ]);
    }

    protected function normalizeSlug(string $value): string
    {
        return Str::slug($value);
    }

    protected function presentCategory(ArchiveCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'entries_count' => (int) ($category->entries_count ?? 0),
        ];
    }

    protected function presentTag(ArchiveTag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'entries_count' => (int) ($tag->entries_count ?? 0),
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
