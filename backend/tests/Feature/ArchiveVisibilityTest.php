<?php

namespace Tests\Feature;

use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTag;
use App\Models\ArchiveTopic;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_topic_can_hide_commander_entry_inside_it(): void
    {
        $member = $this->verifiedUser(rankLevel: 1);
        $topic = $this->archiveTopic([
            'title' => 'Regulations',
            'slug' => 'regulations',
            'minimum_rank_level' => 1,
        ]);

        $this->archiveEntry($topic, [
            'title' => 'Basic Rules',
            'slug' => 'basic-rules',
            'minimum_rank_level' => 1,
        ]);

        $this->archiveEntry($topic, [
            'title' => 'Commander Doctrine',
            'slug' => 'commander-doctrine',
            'minimum_rank_level' => 3,
        ]);

        $response = $this
            ->actingAs($member)
            ->get('/archive/regulations');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Archive/Topic')
                ->where('topic.title', 'Regulations')
                ->where('entries.0.title', 'Basic Rules')
                ->missing('entries.1')
            );
    }

    public function test_direct_restricted_entry_url_returns_not_found_for_lower_rank(): void
    {
        $member = $this->verifiedUser(rankLevel: 1);
        $topic = $this->archiveTopic([
            'slug' => 'regulations',
            'minimum_rank_level' => 1,
        ]);

        $this->archiveEntry($topic, [
            'slug' => 'commander-doctrine',
            'minimum_rank_level' => 3,
        ]);

        $this
            ->actingAs($member)
            ->get('/archive/regulations/commander-doctrine')
            ->assertNotFound();
    }

    public function test_direct_restricted_entry_url_is_visible_to_required_rank(): void
    {
        $commander = $this->verifiedUser(rankLevel: 3);
        $topic = $this->archiveTopic([
            'title' => 'Regulations',
            'slug' => 'regulations',
            'minimum_rank_level' => 1,
        ]);

        $this->archiveEntry($topic, [
            'title' => 'Commander Doctrine',
            'slug' => 'commander-doctrine',
            'minimum_rank_level' => 3,
        ]);

        $response = $this
            ->actingAs($commander)
            ->get('/archive/regulations/commander-doctrine');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Archive/Entry')
                ->where('entry.title', 'Commander Doctrine')
            );
    }

    public function test_archive_search_does_not_leak_restricted_entry_or_taxonomy_metadata(): void
    {
        $member = $this->verifiedUser(rankLevel: 1);
        $topic = $this->archiveTopic([
            'title' => 'Regulations',
            'slug' => 'regulations',
            'minimum_rank_level' => 1,
        ]);

        $category = ArchiveCategory::create([
            'name' => 'Black Vault',
            'slug' => 'black-vault',
            'description' => 'Restricted category marker.',
            'sort_order' => 0,
        ]);

        $tag = ArchiveTag::create([
            'name' => 'Onyx Protocol',
            'slug' => 'onyx-protocol',
        ]);

        $restricted = $this->archiveEntry($topic, [
            'title' => 'Commander Eyes Only',
            'slug' => 'commander-eyes-only',
            'body' => 'This entry contains the onyx protocol details.',
            'minimum_rank_level' => 3,
        ]);

        $restricted->categories()->sync([$category->id]);
        $restricted->tags()->sync([$tag->id]);

        $response = $this
            ->actingAs($member)
            ->get('/archive?search=onyx');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Archive/Index')
                ->where('entries', [])
            );
    }

    public function test_director_role_bypasses_rank_gate(): void
    {
        $director = $this->verifiedUser(rankLevel: 1, roleSlug: 'director');
        $topic = $this->archiveTopic([
            'title' => 'Command Vault',
            'slug' => 'command-vault',
            'minimum_rank_level' => 6,
        ]);

        $this->archiveEntry($topic, [
            'title' => 'Grand Admiral Briefing',
            'slug' => 'grand-admiral-briefing',
            'minimum_rank_level' => 6,
        ]);

        $response = $this
            ->actingAs($director)
            ->get('/archive/command-vault/grand-admiral-briefing');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Archive/Entry')
                ->where('entry.title', 'Grand Admiral Briefing')
            );
    }

    public function test_direct_category_entry_url_returns_not_found_for_lower_rank(): void
    {
        $member = $this->verifiedUser(rankLevel: 1);
        $category = $this->archiveCategory([
            'name' => 'Black Vault',
            'slug' => 'black-vault',
        ]);

        $this->directCategoryEntry($category, [
            'slug' => 'commander-doctrine',
            'minimum_rank_level' => 3,
        ]);

        $this
            ->actingAs($member)
            ->get('/archive/category/black-vault/commander-doctrine')
            ->assertNotFound();
    }

    public function test_direct_category_entry_url_is_visible_to_required_rank(): void
    {
        $commander = $this->verifiedUser(rankLevel: 3);
        $category = $this->archiveCategory([
            'name' => 'Black Vault',
            'slug' => 'black-vault',
        ]);

        $this->directCategoryEntry($category, [
            'title' => 'Commander Doctrine',
            'slug' => 'commander-doctrine',
            'minimum_rank_level' => 3,
        ]);

        $response = $this
            ->actingAs($commander)
            ->get('/archive/category/black-vault/commander-doctrine');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Archive/Entry')
                ->where('category.name', 'Black Vault')
                ->where('topic', null)
                ->where('entry.title', 'Commander Doctrine')
            );
    }

    public function test_category_page_lists_visible_direct_entries(): void
    {
        $member = $this->verifiedUser(rankLevel: 1);
        $category = $this->archiveCategory([
            'name' => 'Black Vault',
            'slug' => 'black-vault',
            'description' => 'Restricted category marker.',
        ]);

        $this->directCategoryEntry($category, [
            'title' => 'Public Notice',
            'slug' => 'public-notice',
            'minimum_rank_level' => 1,
        ]);

        $this->directCategoryEntry($category, [
            'title' => 'Commander Doctrine',
            'slug' => 'commander-doctrine',
            'minimum_rank_level' => 3,
        ]);

        $response = $this
            ->actingAs($member)
            ->get('/archive/category/black-vault');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Archive/Category')
                ->where('category.name', 'Black Vault')
                ->where('entries.0.title', 'Public Notice')
                ->missing('entries.1')
            );
    }

    private function verifiedUser(int $rankLevel, ?string $roleSlug = null): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank' => 'test-rank',
            'rank_level' => $rankLevel,
        ]);

        if ($roleSlug) {
            $role = Role::create([
                'name' => str($roleSlug)->headline()->toString(),
                'slug' => $roleSlug,
                'description' => 'Archive visibility test role.',
                'is_system' => true,
            ]);

            $user->roles()->attach($role->id);
            $user->load('roles');
        }

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
            'excerpt' => 'Test archive entry excerpt.',
            'body' => 'Test archive entry body.',
            'banner_image_path' => null,
            'sort_order' => 0,
            'minimum_rank_level' => null,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }

    private function directCategoryEntry(ArchiveCategory $category, array $attributes = []): ArchiveEntry
    {
        $entry = ArchiveEntry::create(array_merge([
            'archive_topic_id' => null,
            'title' => 'Direct Archive Entry',
            'slug' => 'direct-archive-entry',
            'excerpt' => 'Test direct archive entry excerpt.',
            'body' => 'Test direct archive entry body.',
            'banner_image_path' => null,
            'sort_order' => 0,
            'minimum_rank_level' => null,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ], $attributes));

        $entry->categories()->sync([$category->id]);

        return $entry;
    }
}
