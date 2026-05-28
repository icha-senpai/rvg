<?php

namespace Database\Seeders;

use App\Models\ArchiveCategory;
use App\Models\ArchiveEntry;
use App\Models\ArchiveTag;
use App\Models\ArchiveTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $categories = collect([
                ['name' => 'Doctrine', 'slug' => 'doctrine', 'description' => 'Standing guidance, operating principles, and strategic posture.', 'sort_order' => 10],
                ['name' => 'Procedures', 'slug' => 'procedures', 'description' => 'Step-by-step operational and administrative processes.', 'sort_order' => 20],
                ['name' => 'History', 'slug' => 'history', 'description' => 'Organizational memory, milestones, and records.', 'sort_order' => 30],
                ['name' => 'Logistics', 'slug' => 'logistics', 'description' => 'Transport, support, supply, medical, and recovery coordination.', 'sort_order' => 40],
                ['name' => 'Identity', 'slug' => 'identity', 'description' => 'Purpose, values, culture, and long-term organizational direction.', 'sort_order' => 50],
            ])->mapWithKeys(function (array $categoryData) {
                $category = ArchiveCategory::updateOrCreate(
                    ['slug' => $categoryData['slug']],
                    $categoryData
                );

                return [$category->slug => $category];
            });

            $tags = collect([
                ['name' => 'Defence', 'slug' => 'defence'],
                ['name' => 'Support', 'slug' => 'support'],
                ['name' => 'Medical', 'slug' => 'medical'],
                ['name' => 'Logistics', 'slug' => 'logistics'],
                ['name' => 'Policy', 'slug' => 'policy'],
                ['name' => 'Vision', 'slug' => 'vision'],
                ['name' => 'Starter Content', 'slug' => 'starter-content'],
            ])->mapWithKeys(function (array $tagData) {
                $tag = ArchiveTag::updateOrCreate(
                    ['slug' => $tagData['slug']],
                    $tagData
                );

                return [$tag->slug => $tag];
            });

            $topics = [
                [
                    'title' => 'Defence Industries',
                    'slug' => 'defence-industries',
                    'description' => 'Doctrine, strategic posture, equipment notes, and defence-oriented operational records.',
                    'category_slug' => 'doctrine',
                    'category_label' => 'Defence',
                    'sort_order' => 10,
                    'minimum_rank_level' => 1,
                    'entries' => [
                        [
                            'title' => 'Defence Industries Overview',
                            'slug' => 'defence-industries-overview',
                            'excerpt' => 'A starter overview for Horizon defence doctrine, patrol posture, and security expectations.',
                            'body' => "Defence Industries contains Horizon material related to security, patrol operations, combat readiness, escort posture, and strategic defence planning.\n\nThis Phase 1A article is seeded as a placeholder so the Archive system has live content while the full content management layer is built.",
                            'sort_order' => 10,
                            'minimum_rank_level' => 1,
                            'categories' => ['doctrine'],
                            'tags' => ['defence', 'starter-content'],
                        ],
                    ],
                ],
                [
                    'title' => 'Lifeline Industries',
                    'slug' => 'lifeline-industries',
                    'description' => 'Logistics, rescue, medical support, transport coordination, and operational sustainment.',
                    'category_slug' => 'logistics',
                    'category_label' => 'Support',
                    'sort_order' => 20,
                    'minimum_rank_level' => 1,
                    'entries' => [
                        [
                            'title' => 'Lifeline Support Overview',
                            'slug' => 'lifeline-support-overview',
                            'excerpt' => 'A starter overview for logistics, recovery, medical support, and member assistance workflows.',
                            'body' => "Lifeline Industries tracks the support side of Horizon operations: moving people, supplies, ships, and rescue capability where they are needed.\n\nThis page is intentionally simple for Phase 1A. Later phases can expand it into doctrine, procedures, and structured support articles.",
                            'sort_order' => 10,
                            'minimum_rank_level' => 1,
                            'categories' => ['logistics'],
                            'tags' => ['support', 'medical', 'logistics', 'starter-content'],
                        ],
                    ],
                ],
                [
                    'title' => 'The Vision of Horizon',
                    'slug' => 'the-vision-of-horizon',
                    'description' => 'Purpose, values, long-term objectives, and the identity of Horizon as an organization.',
                    'category_slug' => 'identity',
                    'category_label' => 'Identity',
                    'sort_order' => 30,
                    'minimum_rank_level' => 1,
                    'entries' => [
                        [
                            'title' => 'Horizon Vision Statement',
                            'slug' => 'horizon-vision-statement',
                            'excerpt' => 'A starter statement for Horizon purpose, culture, and long-term direction.',
                            'body' => "Horizon exists to provide organized, welcoming, and scalable Star Citizen experiences across casual, structured, and large-scale operations.\n\nThe Archive will eventually preserve the deeper identity, values, and strategic direction of the organization here.",
                            'sort_order' => 10,
                            'minimum_rank_level' => 1,
                            'categories' => ['identity'],
                            'tags' => ['vision', 'starter-content'],
                        ],
                    ],
                ],
                [
                    'title' => 'Organizational History',
                    'slug' => 'organizational-history',
                    'description' => 'Milestones, leadership records, historical events, and important organizational developments.',
                    'category_slug' => 'history',
                    'category_label' => 'Records',
                    'sort_order' => 40,
                    'minimum_rank_level' => 1,
                    'entries' => [
                        [
                            'title' => 'Archive History Placeholder',
                            'slug' => 'archive-history-placeholder',
                            'excerpt' => 'A starter historical record used to validate the Archive article layout.',
                            'body' => "Organizational History will hold important milestones, records, transitions, and long-term memory for Horizon.\n\nThis seeded article is a placeholder for validating navigation, search, and visibility rules.",
                            'sort_order' => 10,
                            'minimum_rank_level' => 1,
                            'categories' => ['history'],
                            'tags' => ['starter-content'],
                        ],
                    ],
                ],
                [
                    'title' => 'Regulations and Procedures',
                    'slug' => 'regulations-and-procedures',
                    'description' => 'Operational standards, policies, internal guidance, and procedure references.',
                    'category_slug' => 'procedures',
                    'category_label' => 'Policy',
                    'sort_order' => 50,
                    'minimum_rank_level' => 2,
                    'entries' => [
                        [
                            'title' => 'Procedure Index Placeholder',
                            'slug' => 'procedure-index-placeholder',
                            'excerpt' => 'A restricted starter article for officer-facing policy and procedure references.',
                            'body' => "Regulations and Procedures is rank-gated in Phase 1A so the access system can be tested immediately.\n\nMembers below the required rank should not see this topic, this article, its title, its excerpt, or search results that reference it.",
                            'sort_order' => 10,
                            'minimum_rank_level' => 2,
                            'categories' => ['procedures'],
                            'tags' => ['policy', 'starter-content'],
                        ],
                    ],
                ],
            ];

            foreach ($topics as $topicData) {
                $entries = $topicData['entries'];
                $categorySlug = $topicData['category_slug'] ?? null;
                unset($topicData['entries']);
                unset($topicData['category_slug']);

                $topic = ArchiveTopic::updateOrCreate(
                    ['slug' => $topicData['slug']],
                    array_merge($topicData, [
                        'archive_category_id' => $categorySlug ? $categories->get($categorySlug)?->id : null,
                        'is_published' => true,
                        'published_at' => $now,
                    ])
                );

                foreach ($entries as $entryData) {
                    $entryCategories = $entryData['categories'] ?? [];
                    $entryTags = $entryData['tags'] ?? [];
                    unset($entryData['categories'], $entryData['tags']);

                    $entry = ArchiveEntry::updateOrCreate(
                        [
                            'archive_topic_id' => $topic->id,
                            'slug' => $entryData['slug'],
                        ],
                        array_merge($entryData, [
                            'archive_topic_id' => $topic->id,
                            'is_published' => true,
                            'published_at' => $now,
                        ])
                    );

                    $entry->categories()->sync(
                        collect($entryCategories)
                            ->map(fn (string $slug) => $categories->get($slug)?->id)
                            ->filter()
                            ->values()
                            ->all()
                    );

                    $entry->tags()->sync(
                        collect($entryTags)
                            ->map(fn (string $slug) => $tags->get($slug)?->id)
                            ->filter()
                            ->values()
                            ->all()
                    );
                }
            }
        });
    }
}
