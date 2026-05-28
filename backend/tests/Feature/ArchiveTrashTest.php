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

class ArchiveTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_topic_moves_topic_and_entries_to_trash(): void
    {
        $admin = $this->adminUser();
        $topic = $this->archiveTopic();
        $entry = $this->archiveEntry($topic);

        $this
            ->actingAs($admin)
            ->delete(route('admin.archive.topics.destroy', $topic))
            ->assertRedirect(route('admin.archive.index'));

        $this->assertSoftDeleted('archive_topics', [
            'id' => $topic->id,
        ]);

        $this->assertSoftDeleted('archive_entries', [
            'id' => $entry->id,
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.topic.deleted',
        ]);
    }

    public function test_restoring_topic_restores_its_entries(): void
    {
        $admin = $this->adminUser();
        $topic = $this->archiveTopic();
        $entry = $this->archiveEntry($topic);

        $topic->entries()->delete();
        $topic->delete();

        $this
            ->actingAs($admin)
            ->post(route('admin.archive.trash.topics.restore', $topic->id))
            ->assertRedirect();

        $this->assertDatabaseHas('archive_topics', [
            'id' => $topic->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('archive_entries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.topic.restored',
        ]);
    }

    public function test_force_deleting_topic_permanently_removes_topic_and_entries(): void
    {
        $admin = $this->adminUser();
        $topic = $this->archiveTopic();
        $entry = $this->archiveEntry($topic);

        $topic->entries()->delete();
        $topic->delete();

        $this
            ->actingAs($admin)
            ->delete(route('admin.archive.trash.topics.force-delete', $topic->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('archive_topics', [
            'id' => $topic->id,
        ]);

        $this->assertDatabaseMissing('archive_entries', [
            'id' => $entry->id,
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.topic.force_deleted',
        ]);
    }

    public function test_trash_page_hides_entries_bundled_under_deleted_topics(): void
    {
        $admin = $this->adminUser();
        $topic = $this->archiveTopic();
        $this->archiveEntry($topic);

        $topic->entries()->delete();
        $topic->delete();

        $this
            ->actingAs($admin)
            ->get(route('admin.archive.trash.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ArchiveTrash')
                ->where('topics.0.title', 'Archive Topic')
                ->where('entries', [])
            );
    }

    public function test_restoring_single_entry_keeps_topic_active(): void
    {
        $admin = $this->adminUser();
        $topic = $this->archiveTopic();
        $entry = $this->archiveEntry($topic);

        $entry->delete();

        $this
            ->actingAs($admin)
            ->post(route('admin.archive.trash.entries.restore', $entry->id))
            ->assertRedirect();

        $this->assertDatabaseHas('archive_topics', [
            'id' => $topic->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('archive_entries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'archive.entry.restored',
        ]);
    }

    private function adminUser(): User
    {
        $role = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Archive trash test director role.',
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
            'archive_category_id' => $this->archiveCategory()->id,
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

    private function archiveCategory(array $attributes = []): ArchiveCategory
    {
        $payload = array_merge([
            'name' => 'Test',
            'slug' => 'test',
            'description' => null,
            'sort_order' => 0,
        ], $attributes);

        return ArchiveCategory::query()->updateOrCreate(
            ['slug' => $payload['slug']],
            $payload,
        );
    }

    private function archiveEntry(ArchiveTopic $topic, array $attributes = []): ArchiveEntry
    {
        return ArchiveEntry::create(array_merge([
            'archive_topic_id' => $topic->id,
            'title' => 'Archive Entry',
            'slug' => 'archive-entry',
            'excerpt' => 'Test archive entry.',
            'body' => '<p>Entry body.</p>',
            'banner_image_path' => null,
            'sort_order' => 0,
            'minimum_rank_level' => null,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }
}
