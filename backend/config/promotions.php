<?php

return [
    'ladder' => [
        'member',
        'cit',
        'commander',
        'wing_commander',
        'admiral',
        'grand_admiral',
    ],

    'offerable_targets' => [
        'cit',
        'commander',
        'wing_commander',
        'admiral',
        'grand_admiral',
    ],

    'labels' => [
        'member' => 'Member',
        'cit' => 'C.I.T (Commander in Training)',
        'commander' => 'Commander',
        'wing_commander' => 'Wing Commander',
        'admiral' => 'Admiral',
        'grand_admiral' => 'Grand Admiral',
        'director' => 'Director',
        'tech_director' => 'Technical Director',
    ],

    'expires_after_minutes' => 240,
    'accept_emoji' => env('PROMOTION_ACCEPT_EMOJI', '✅'),

    'authorities' => [
        'admiral' => ['cit', 'commander'],
        'grand_admiral' => ['cit', 'commander', 'wing_commander'],
        'director' => ['cit', 'commander', 'wing_commander', 'admiral', 'grand_admiral'],
        'tech_director' => ['cit', 'commander', 'wing_commander', 'admiral', 'grand_admiral'],
    ],

    'discord' => [
        'rank_role_ids' => [
            'member' => env('DISCORD_ROLE_MEMBER_ID'),
            'cit' => env('DISCORD_ROLE_CIT_ID', '1454412898658029705'),
            'commander' => env('DISCORD_ROLE_COMMANDER_ID', '1454412897370505236'),
            'wing_commander' => env('DISCORD_ROLE_WING_COMMANDER_ID', '1454412889682346007'),
            'admiral' => env('DISCORD_ROLE_ADMIRAL_ID', '1454412866554822677'),
            'grand_admiral' => env('DISCORD_ROLE_GRAND_ADMIRAL_ID', '1454412856698077380'),
        ],

        'authority_role_ids' => [
            'assistant_director' => env('DISCORD_ROLE_ASSISTANT_DIRECTOR_ID', '1459959752724316233'),
            'director' => env('DISCORD_ROLE_DIRECTOR_ID', '1454412851153469484'),
        ],

        'quarter_channel_ids' => [
            'cit' => env('PROMOTION_CIT_QUARTER_CHANNEL_ID', '1454413032104136754'),
            'commander' => env('PROMOTION_COMMANDER_QUARTER_CHANNEL_ID', '1454413032104136754'),
            'wing_commander' => env('PROMOTION_WING_COMMANDER_QUARTER_CHANNEL_ID', '1454413030837325844'),
            'admiral' => env('PROMOTION_ADMIRAL_QUARTER_CHANNEL_ID'),
            'grand_admiral' => env('PROMOTION_GRAND_ADMIRAL_QUARTER_CHANNEL_ID'),
        ],

        'branch_roles' => [
            'cit' => [
                [
                    'id' => env('PROMOTION_BRANCH_CIT_LIFELINES_ROLE_ID', '1454412925312696331'),
                    'label' => 'Lifelines',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_CIT_INDUSTRIES_ROLE_ID', '1454412922569621618'),
                    'label' => 'Industries',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_CIT_DEFENCE_ROLE_ID', '1454412920963203167'),
                    'label' => 'Defence',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_CIT_FRONTIERS_ROLE_ID', '1454412923790299178'),
                    'label' => 'Frontiers',
                ],
            ],
            'commander' => [
                [
                    'id' => env('PROMOTION_BRANCH_COMMANDER_LIFELINES_ROLE_ID', '1454412925312696331'),
                    'label' => 'Lifelines',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_COMMANDER_INDUSTRIES_ROLE_ID', '1454412922569621618'),
                    'label' => 'Industries',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_COMMANDER_DEFENCE_ROLE_ID', '1454412920963203167'),
                    'label' => 'Defence',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_COMMANDER_FRONTIERS_ROLE_ID', '1454412923790299178'),
                    'label' => 'Frontiers',
                ],
            ],
            'wing_commander' => [
                [
                    'id' => env('PROMOTION_BRANCH_WING_COMMANDER_LIFELINES_ROLE_ID', '1454412925312696331'),
                    'label' => 'Lifelines',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_WING_COMMANDER_INDUSTRIES_ROLE_ID', '1454412922569621618'),
                    'label' => 'Industries',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_WING_COMMANDER_DEFENCE_ROLE_ID', '1454412920963203167'),
                    'label' => 'Defence',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_WING_COMMANDER_FRONTIERS_ROLE_ID', '1454412923790299178'),
                    'label' => 'Frontiers',
                ],
            ],
            'admiral' => [
                [
                    'id' => env('PROMOTION_BRANCH_ADMIRAL_LIFELINES_ROLE_ID', '1454412925312696331'),
                    'label' => 'Lifelines',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_ADMIRAL_INDUSTRIES_ROLE_ID', '1454412922569621618'),
                    'label' => 'Industries',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_ADMIRAL_DEFENCE_ROLE_ID', '1454412920963203167'),
                    'label' => 'Defence',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_ADMIRAL_FRONTIERS_ROLE_ID', '1454412923790299178'),
                    'label' => 'Frontiers',
                ],
            ],
            'grand_admiral' => [
                [
                    'id' => env('PROMOTION_BRANCH_GRAND_ADMIRAL_LIFELINES_ROLE_ID', '1454412925312696331'),
                    'label' => 'Lifelines',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_GRAND_ADMIRAL_INDUSTRIES_ROLE_ID', '1454412922569621618'),
                    'label' => 'Industries',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_GRAND_ADMIRAL_DEFENCE_ROLE_ID', '1454412920963203167'),
                    'label' => 'Defence',
                ],
                [
                    'id' => env('PROMOTION_BRANCH_GRAND_ADMIRAL_FRONTIERS_ROLE_ID', '1454412923790299178'),
                    'label' => 'Frontiers',
                ],
            ],
        ],
    ],

    'dm_templates' => [
        'cit' => <<<'TEXT'
Congratulations, :member_name.

This message has been issued at the request of **:promoter_rank_label :promoter_name**.

Your recent activity, dedication, and contributions within the **:branch_label** branch have been noticed by the Admiralty. Due to your continued involvement, reliability, and positive impact, you have been selected as a candidate for the **C.I.T Program** within the **:branch_label** branch.

The C.I.T phase will last **3 months**. Upon successful completion of all requirements and a final evaluation, your rank will be upgraded to **Commander**.

## Requirements for Promotion to Commander

*To be completed by the end of the 3rd month:*

* Create and host at least **2 operations per month**, for a minimum total of **6 operations** by the end of your C.I.T phase.
* Maintain regular activity in chats, voice channels, and operations.
* Demonstrate leadership, reliability, and the ability to support and guide members when needed.

## Abilities Granted During the C.I.T Phase

* Access to the **operation tool**.
* Permission to manage members in selected voice channels.

During your time as C.I.T, a senior officer will guide you, monitor your progress, and evaluate your readiness for final promotion to **Commander** at the end of the **3-month phase**.

By accepting this offer, you confirm that you have read, understood, and agreed to all terms and expectations listed above.

To accept this offer, please react with :accept_emoji.

-# This offer will remain available for the next **4 hours**.
TEXT,
        'commander' => <<<'TEXT'
Congratulations, :member_name.

This promotion offer has been issued at the request of **:promoter_rank_label :promoter_name**.

Your C.I.T. phase has been successfully completed, and the senior officer assigned to you has signed off on your promotion. Through your continued efforts, dedication, and proven value as a Commander in Training, you are hereby appointed as a **Commander of the :branch_label Branch**.

Keep up the excellent work. The doors are now open for you to explore what it truly means to be a Commander of Horizon.

## New Permission Granted with This Promotion

* Ability to create a **Squadron**.

By accepting this offer, you confirm that you have read, understood, and agreed to everything mentioned above.

To accept this promotion, please react with :accept_emoji.

-# This offer may be accepted for the next **4 hours**.
TEXT,
        'wing_commander' => <<<'TEXT'
Congratulations, :member_name.

This offer has been issued at the request of **:promoter_rank_label :promoter_name**.

You have consistently proven yourself as a Commander through your leadership, reliability, and commitment to Horizon. Your dedication to improving both yourself and those around you has made a clear and positive impact on the organisation.

Your ability to identify areas that need improvement, take initiative, and act in the best interest of Horizon as a whole has not gone unnoticed. You have shown that you are capable of leading with confidence, supporting others, and upholding the standards expected of higher command.

This promotion offer reflects the trust placed in you by High Command and the belief that you are ready to take on greater responsibility within Horizon!

## Minimum Requirements

To accept this promotion, you must be willing and able to meet the following expectations:

* Create and lead a **Squadron**.
* Create and host at least **2 operations per month**.
* Create and host a **Beginner’s Guide to Horizon** every two months. This counts toward the 2 operations per month requirement.
* Maintain a strong understanding of the organisation rules, Horizon’s vision, and the Horizon Rules of Engagement **(ROEs)**.
* Demonstrate strong skills in using secondary command communications and effectively organising larger operations.

## New Permissions Granted

With this promotion, you will be granted the following permissions:

* Ability to form a **Wing**.
* Ability to host and lead operations with more than 40 players.
* Ability to host **Beginner’s Guides to Horizon**.
* Ability to declare no-fly zones during operations.
* Ability to guide a **C.I.T.** through their 3 months of training.
* Ability to review member stats.

By accepting this offer, you confirm that you have read, understood, and agreed to everything listed above.

To accept this promotion, please react with :accept_emoji.

-# This offer will remain valid for the next **4 hours**.
TEXT,
        'admiral' => '',
        'grand_admiral' => '',
    ],

    'quarter_templates' => [
        'cit' => ':member_name has been offered promotion to :rank_label in the :branch_label branch at the request of :promoter_rank_label :promoter_name.',
        'commander' => ':member_name has been offered promotion to :rank_label in the :branch_label branch at the request of :promoter_rank_label :promoter_name.',
        'wing_commander' => ':member_name has been offered promotion to :rank_label in the :branch_label branch at the request of :promoter_rank_label :promoter_name.',
        'admiral' => ':member_name has been offered promotion to :rank_label in the :branch_label branch at the request of :promoter_rank_label :promoter_name.',
        'grand_admiral' => ':member_name has been offered promotion to :rank_label in the :branch_label branch at the request of :promoter_rank_label :promoter_name.',
    ],
];
