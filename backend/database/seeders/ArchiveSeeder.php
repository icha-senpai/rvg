<?php

namespace Database\Seeders;

use App\Models\ArchiveEntry;
use App\Models\ArchiveTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $topics = [
                [
                    'title' => 'Defence Industries',
                    'slug' => 'defence-industries',
                    'description' => 'Doctrine, strategic posture, equipment notes, and defence-oriented operational records.',
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
                        ],
                    ],
                ],
                [
                    'title' => 'Lifeline Industries',
                    'slug' => 'lifeline-industries',
                    'description' => 'Logistics, rescue, medical support, transport coordination, and operational sustainment.',
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
                        ],
                    ],
                ],
                [
                    'title' => 'The Vision of Horizon',
                    'slug' => 'the-vision-of-horizon',
                    'description' => 'Purpose, values, long-term objectives, and the identity of Horizon as an organization.',
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
                        ],
                    ],
                ],
                [
                    'title' => 'Organizational History',
                    'slug' => 'organizational-history',
                    'description' => 'Milestones, leadership records, historical events, and important organizational developments.',
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
                        ],
                    ],
                ],
                [
                    'title' => 'Regulations and Procedures',
                    'slug' => 'regulations-and-procedures',
                    'description' => 'Operational standards, policies, internal guidance, and procedure references.',
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
                        ],
                    ],
                ],
            ];

            foreach ($topics as $topicData) {
                $entries = $topicData['entries'];
                unset($topicData['entries']);

                $topic = ArchiveTopic::updateOrCreate(
                    ['slug' => $topicData['slug']],
                    array_merge($topicData, [
                        'is_published' => true,
                        'published_at' => $now,
                    ])
                );

                foreach ($entries as $entryData) {
                    ArchiveEntry::updateOrCreate(
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
                }
            }
        });
    }
}
