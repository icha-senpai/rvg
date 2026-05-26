<?php

namespace Tests\Feature;

use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTopic;
use App\Models\AuthAuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_create_update_and_delete_are_audit_logged(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->post(route('admin.archive.topics.store'), [
                'title' => 'Doctrine',
                'slug' => 'doctrine',
                'description' => 'Operational doctrine.',
                'category_label' => 'Policy',
                'card_image_path' => null,
                'banner_image_path' => null,
                'sort_order' => 0,
                'minimum_rank_level' => 1,
                'is_published' => true,
            ])
            ->assertRedirect(route('admin.archive.index'));

        $topic = ArchiveTopic::query()->where('slug', 'doctrine')->firstOrFail();

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.topic.created',
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.archive.topics.update', $topic), [
                'title' => 'Doctrine Updated',
                'slug' => 'doctrine-updated',
                'description' => 'Updated doctrine.',
                'category_label' => 'Policy',
                'card_image_path' => null,
                'banner_image_path' => null,
                'sort_order' => 1,
                'minimum_rank_level' => 2,
                'is_published' => true,
            ])
            ->assertRedirect(route('admin.archive.index'));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.topic.updated',
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.archive.topics.destroy', $topic))
            ->assertRedirect(route('admin.archive.index'));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.topic.deleted',
        ]);
    }

    public function test_entry_create_update_and_delete_are_audit_logged(): void
    {
        $admin = $this->adminUser();
        $topic = $this->archiveTopic();
        $category = ArchiveCategory::create([
            'name' => 'Doctrine',
            'slug' => 'doctrine',
            'description' => null,
            'sort_order' => 0,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.archive.topics.entries.store', $topic), [
                'title' => 'Entry One',
                'slug' => 'entry-one',
                'excerpt' => 'Entry excerpt.',
                'body' => 'Entry body.',
                'banner_image_path' => null,
                'sort_order' => 0,
                'minimum_rank_level' => 1,
                'is_published' => true,
                'category_ids' => [$category->id],
                'tag_ids' => [],
            ])
            ->assertRedirect(route('admin.archive.topics.entries.index', $topic));

        $entry = ArchiveEntry::query()->where('slug', 'entry-one')->firstOrFail();

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.entry.created',
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.archive.topics.entries.update', [$topic, $entry]), [
                'title' => 'Entry One Updated',
                'slug' => 'entry-one-updated',
                'excerpt' => 'Updated excerpt.',
                'body' => 'Updated body.',
                'banner_image_path' => null,
                'sort_order' => 1,
                'minimum_rank_level' => 2,
                'is_published' => true,
                'category_ids' => [],
                'tag_ids' => [],
            ])
            ->assertRedirect(route('admin.archive.topics.entries.index', $topic));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.entry.updated',
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.archive.topics.entries.destroy', [$topic, $entry]))
            ->assertRedirect(route('admin.archive.topics.entries.index', $topic));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.entry.deleted',
        ]);
    }

    public function test_category_and_tag_changes_are_audit_logged(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->post(route('admin.archive.taxonomy.categories.store'), [
                'name' => 'Policy',
                'slug' => 'policy',
                'description' => 'Policy docs.',
                'sort_order' => 0,
            ])
            ->assertRedirect(route('admin.archive.taxonomy.index'));

        $category = ArchiveCategory::query()->where('slug', 'policy')->firstOrFail();

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.category.created',
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.archive.taxonomy.categories.update', $category), [
                'name' => 'Policy Updated',
                'slug' => 'policy-updated',
                'description' => 'Updated policy docs.',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.archive.taxonomy.index'));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.category.updated',
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.archive.taxonomy.categories.destroy', $category))
            ->assertRedirect(route('admin.archive.taxonomy.index'));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.category.deleted',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.archive.taxonomy.tags.store'), [
                'name' => 'Operations',
                'slug' => 'operations',
            ])
            ->assertRedirect(route('admin.archive.taxonomy.index'));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.tag.created',
        ]);
    }

    public function test_audit_log_page_lists_archive_events(): void
    {
        $admin = $this->adminUser();

        AuthAuditLog::create([
            'user_id' => $admin->id,
            'action' => 'archive.topic.created',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'meta' => [
                'topic_id' => 1,
                'title' => 'Doctrine',
                'slug' => 'doctrine',
            ],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.archive.audit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ArchiveAudit')
                ->where('logs.data.0.action', 'archive.topic.created')
                ->where('logs.data.0.summary', 'Created topic: Doctrine')
            );
    }

    private function adminUser(): User
    {
        $role = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Archive audit test director role.',
            'is_system' => true,
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank' => 'director',
            'rank_level' => 6,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        return $user;
    }

    private function archiveTopic(array $attributes = []): ArchiveTopic
    {
        return ArchiveTopic::create(array_merge([
            'title' => 'Archive Topic',
            'slug' => 'archive-topic',
            'description' => 'Test archive topic.',
            'category_label' => 'Test',
            'card_image_path' => null,
            'banner_image_path' => null,
            'sort_order' => 0,
            'minimum_rank_level' => null,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }
}
