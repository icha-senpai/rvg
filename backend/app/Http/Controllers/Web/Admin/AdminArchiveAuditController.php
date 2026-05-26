<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminArchiveAuditController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('access-admin-panel');

        $filters = [
            'action' => $request->query('action'),
            'search' => trim((string) $request->query('search', '')),
        ];

        $logs = AuthAuditLog::query()
            ->with('user:id,rsi_handle,discord_name')
            ->where('action', 'like', 'archive.%')
            ->when($filters['action'], fn ($query, $action) => $query->where('action', $action))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']) . '%';

                $query->where(function ($inner) use ($search) {
                    $inner->where('action', 'like', $search)
                        ->orWhere('meta->title', 'like', $search)
                        ->orWhere('meta->name', 'like', $search)
                        ->orWhere('meta->slug', 'like', $search)
                        ->orWhere('meta->topic_title', 'like', $search);
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $logs->setCollection(
            $logs->getCollection()->map(fn (AuthAuditLog $log) => $this->presentLog($log))
        );

        return Inertia::render('Admin/ArchiveAudit', [
            'logs' => $logs,
            'filters' => $filters,
            'actionOptions' => $this->actionOptions(),
        ]);
    }

    protected function presentLog(AuthAuditLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'summary' => $this->summaryFor($log),
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'meta' => $log->meta ?? [],
            'user' => $log->user ? [
                'id' => $log->user->id,
                'rsi_handle' => $log->user->rsi_handle,
                'discord_name' => $log->user->discord_name,
            ] : null,
            'created_label' => $log->created_at?->format('M j, Y g:i A'),
        ];
    }

    protected function summaryFor(AuthAuditLog $log): string
    {
        $meta = $log->meta ?? [];
        $name = $meta['title'] ?? $meta['name'] ?? $meta['slug'] ?? 'Archive item';

        return match ($log->action) {
            'archive.topic.created' => "Created topic: {$name}",
            'archive.topic.updated' => "Updated topic: {$name}",
            'archive.topic.deleted' => "Deleted topic: {$name}",
            'archive.entry.created' => "Created entry: {$name}",
            'archive.entry.updated' => "Updated entry: {$name}",
            'archive.entry.deleted' => "Deleted entry: {$name}",
            'archive.category.created' => "Created category: {$name}",
            'archive.category.updated' => "Updated category: {$name}",
            'archive.category.deleted' => "Deleted category: {$name}",
            'archive.tag.created' => "Created tag: {$name}",
            'archive.tag.updated' => "Updated tag: {$name}",
            'archive.tag.deleted' => "Deleted tag: {$name}",
            default => $log->action,
        };
    }

    protected function actionOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All archive actions'],
            ['value' => 'archive.topic.created', 'label' => 'Topic created'],
            ['value' => 'archive.topic.updated', 'label' => 'Topic updated'],
            ['value' => 'archive.topic.deleted', 'label' => 'Topic deleted'],
            ['value' => 'archive.entry.created', 'label' => 'Entry created'],
            ['value' => 'archive.entry.updated', 'label' => 'Entry updated'],
            ['value' => 'archive.entry.deleted', 'label' => 'Entry deleted'],
            ['value' => 'archive.category.created', 'label' => 'Category created'],
            ['value' => 'archive.category.updated', 'label' => 'Category updated'],
            ['value' => 'archive.category.deleted', 'label' => 'Category deleted'],
            ['value' => 'archive.tag.created', 'label' => 'Tag created'],
            ['value' => 'archive.tag.updated', 'label' => 'Tag updated'],
            ['value' => 'archive.tag.deleted', 'label' => 'Tag deleted'],
        ];
    }
}
