<?php

namespace Tests\Feature;

use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransaction;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationSettlement;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationSettlementWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_operation_can_save_settlement_draft_and_payloads_include_eligible_recipients_and_uex_loot_options(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'PaidPilot']);
        $squadron = $this->activeSquadron($creator, 'Aurora Wing');
        $supportSquadron = $this->activeSquadron($creator, 'Polaris Wing');
        $operation = $this->completedOperation($creator, $squadron, [
            'squadron_name' => "{$squadron->name}, {$supportSquadron->name}",
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->put(route('operations.settlement.update', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'money-1',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 5000,
                        'notes' => 'Lead payout',
                    ],
                ],
                'loot_rows' => [
                    [
                        'row_key' => 'loot-1',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => 'organization',
                        'quantity' => 12,
                        'unit_label' => 'SCU',
                        'notes' => 'Recovered cargo',
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'Operation settlement draft saved.');

        $settlement = OperationSettlement::query()->where('operation_id', $operation->id)->firstOrFail();

        $this->assertSame('member', $settlement->money_rows[0]['recipient_type']);
        $this->assertSame('commodity', $settlement->loot_rows[0]['source_type']);

        $this->actingAs($creator)
            ->get('/operations/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationDashboard')
                ->where('afterActionOperations.0.id', $operation->id)
                ->where('afterActionOperations.0.operation_settlement.money_rows.0.notes', 'Lead payout')
                ->where('afterActionOperations.0.operation_settlement.activity.finalized_at', null)
                ->where('afterActionOperations.0.operation_settlement.eligible_recipients.0.recipient_squadron_id', $squadron->id)
                ->where('afterActionOperations.0.operation_settlement.eligible_recipients.1.recipient_squadron_id', $supportSquadron->id)
                ->where('afterActionOperations.0.operation_settlement.eligible_recipients.2.recipient_type', 'organization')
                ->where('afterActionOperations.0.operation_settlement.eligible_recipients.3.recipient_user_id', $participant->id)
                ->where('afterActionOperations.0.operation_settlement.loot_options', null)
                ->where('operationSettlementLootOptions.commodities.0.label', 'Agricium')
                ->where('operationSettlementLootOptions.items.0.label', 'Atlas Drive · Quantum Drive')
            );

        $this->actingAs($creator)
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('operations.0.id', $operation->id)
                ->where('operations.0.operation_settlement.loot_rows.0.reference_label', 'Agricium')
                ->where('operations.0.operation_settlement.permissions.can_manage', true)
                ->where('operationSettlementLootOptions.commodities.0.label', 'Agricium')
            );
    }

    public function test_settlement_draft_save_preserves_review_rows_and_drops_blank_noise_in_web_flow(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'MixedDraftPilot']);
        $squadron = $this->activeSquadron($creator, 'Nova Wing');
        $operation = $this->completedOperation($creator, $squadron, [
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->put(route('operations.settlement.update', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'blank-money',
                        'recipient_type' => null,
                        'recipient_user_id' => null,
                        'recipient_squadron_id' => null,
                        'amount' => null,
                        'notes' => '   ',
                    ],
                    [
                        'row_key' => 'review-money',
                        'amount' => 2500,
                        'notes' => ' Needs review ',
                    ],
                    [
                        'row_key' => 'member-money',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 4200,
                        'notes' => ' Crew cut ',
                    ],
                ],
                'loot_rows' => [
                    [
                        'row_key' => 'blank-loot',
                        'source_type' => null,
                        'uex_reference_id' => null,
                        'recipient_type' => null,
                        'recipient_user_id' => null,
                        'recipient_squadron_id' => null,
                        'quantity' => null,
                        'unit_label' => ' ',
                        'notes' => ' ',
                    ],
                    [
                        'row_key' => 'review-loot',
                        'source_type' => 'component',
                        'uex_reference_id' => 9101,
                        'recipient_type' => null,
                        'recipient_user_id' => null,
                        'recipient_squadron_id' => null,
                        'quantity' => 2,
                        'unit_label' => '',
                        'notes' => ' Salvage module ',
                    ],
                    [
                        'row_key' => 'org-loot',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => 'organization',
                        'quantity' => 6,
                        'unit_label' => '',
                        'notes' => ' Cargo reserve ',
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'Operation settlement draft saved.');

        $settlement = OperationSettlement::query()->where('operation_id', $operation->id)->firstOrFail();

        $this->assertCount(2, $settlement->money_rows ?? []);
        $this->assertSame('review-money', $settlement->money_rows[0]['row_key']);
        $this->assertNull($settlement->money_rows[0]['recipient_type']);
        $this->assertSame(2500, $settlement->money_rows[0]['amount']);
        $this->assertSame('Needs review', $settlement->money_rows[0]['notes']);
        $this->assertSame('member', $settlement->money_rows[1]['recipient_type']);
        $this->assertSame($participant->id, $settlement->money_rows[1]['recipient_user_id']);
        $this->assertSame('Crew cut', $settlement->money_rows[1]['notes']);

        $this->assertCount(2, $settlement->loot_rows ?? []);
        $this->assertSame('review-loot', $settlement->loot_rows[0]['row_key']);
        $this->assertSame('component', $settlement->loot_rows[0]['source_type']);
        $this->assertSame('item', $settlement->loot_rows[0]['uex_reference_type']);
        $this->assertSame('Atlas Drive', $settlement->loot_rows[0]['reference_label']);
        $this->assertNull($settlement->loot_rows[0]['recipient_type']);
        $this->assertNull($settlement->loot_rows[0]['unit_label']);
        $this->assertSame('Salvage module', $settlement->loot_rows[0]['notes']);
        $this->assertSame('org-loot', $settlement->loot_rows[1]['row_key']);
        $this->assertSame('organization', $settlement->loot_rows[1]['recipient_type']);
        $this->assertSame('Agricium', $settlement->loot_rows[1]['reference_label']);
        $this->assertNull($settlement->loot_rows[1]['unit_label']);
        $this->assertSame('Cargo reserve', $settlement->loot_rows[1]['notes']);
    }

    public function test_operation_settlement_rejects_decimal_payouts_and_loot_quantities(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'WholeNumberPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->put(route('operations.settlement.update', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'money-1',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 5000.5,
                    ],
                ],
                'loot_rows' => [
                    [
                        'row_key' => 'loot-1',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'quantity' => 12.5,
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors([
                'money_rows.0.amount',
                'loot_rows.0.quantity',
            ]);
    }

    public function test_finalizing_saved_decimal_settlement_draft_is_blocked_until_rows_are_whole_numbers(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'LegacyDraftPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [
                [
                    'row_key' => 'money-1',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 1500.5,
                ],
            ],
            'loot_rows' => [],
        ]);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('money_rows');
    }

    public function test_finalizing_saved_mixed_settlement_draft_is_blocked_until_review_rows_are_resolved(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'MixedFinalizePilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 4200,
                    'notes' => 'Crew cut',
                ],
            ],
            'loot_rows' => [
                [
                    'row_key' => 'review-loot',
                    'source_type' => 'component',
                    'uex_reference_type' => 'item',
                    'uex_reference_id' => 9101,
                    'reference_label' => 'Atlas Drive',
                    'recipient_type' => null,
                    'recipient_user_id' => null,
                    'recipient_squadron_id' => null,
                    'quantity' => 2,
                    'unit_label' => null,
                    'notes' => 'Needs routing',
                ],
            ],
        ]);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('loot_rows');
    }

    public function test_finalizing_operation_settlement_rejects_member_not_in_final_attendance_roster(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'OffRosterPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'member-pay',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 2500,
                    ],
                ],
                'loot_rows' => [],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('recipient_user_id');
    }

    public function test_finalizing_operation_settlement_rejects_squadron_destination_for_non_squadron_operation(): void
    {
        $creator = $this->directorUser();
        $squadron = $this->activeSquadron($creator, 'Invalid Recipient Wing');
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'squadron-pay',
                        'recipient_type' => 'squadron',
                        'recipient_squadron_id' => $squadron->id,
                        'amount' => 1800,
                    ],
                ],
                'loot_rows' => [],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('recipient_type');
    }

    public function test_finalizing_operation_settlement_rejects_invalid_loot_reference(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'LootPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [],
                'loot_rows' => [
                    [
                        'row_key' => 'loot-member',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 999999,
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'quantity' => 4,
                        'unit_label' => 'SCU',
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('loot_rows');
    }

    public function test_finalizing_operation_settlement_creates_locked_ledger_receipts_and_locks_attendance(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'HaulerOne']);
        $squadron = $this->activeSquadron($creator, 'Mercury Wing');
        $supportSquadron = $this->activeSquadron($creator, 'Starlight Wing');
        $operation = $this->completedOperation($creator, $squadron, [
            'squadron_name' => "{$squadron->name}, {$supportSquadron->name}",
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'member-pay',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 7200,
                        'notes' => 'Crew cut',
                    ],
                    [
                        'row_key' => 'squadron-pay',
                        'recipient_type' => 'squadron',
                        'recipient_squadron_id' => $supportSquadron->id,
                        'amount' => 1800,
                        'notes' => 'Squadron reserve',
                    ],
                    [
                        'row_key' => 'org-pay',
                        'recipient_type' => 'organization',
                        'amount' => 600,
                        'notes' => 'Treasury tithe',
                    ],
                ],
                'loot_rows' => [
                    [
                        'row_key' => 'loot-member',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'quantity' => 8,
                        'unit_label' => 'SCU',
                    ],
                    [
                        'row_key' => 'loot-squadron',
                        'source_type' => 'component',
                        'uex_reference_id' => 9101,
                        'recipient_type' => 'squadron',
                        'recipient_squadron_id' => $supportSquadron->id,
                        'quantity' => 2,
                        'unit_label' => 'units',
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'Operation settlement finalized.');

        $settlement = OperationSettlement::query()->where('operation_id', $operation->id)->firstOrFail();

        $this->assertNotNull($settlement->finalized_at);

        $this->actingAs($creator)
            ->get('/operations/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationDashboard')
                ->where('afterActionOperations.0.operation_settlement.is_finalized', true)
                ->where('afterActionOperations.0.operation_settlement.activity.finalized_by', $creator->rsi_handle ?? $creator->discord_name ?? $creator->name)
            );

        $this->assertDatabaseHas('ledger_transactions', [
            'operation_settlement_id' => $settlement->id,
            'related_operation_id' => $operation->id,
            'type' => 'income',
            'amount' => 7200,
            'source_type' => 'operation_settlement',
            'provenance_locked' => 1,
        ]);

        $this->assertDatabaseHas('ledger_transactions', [
            'operation_settlement_id' => $settlement->id,
            'related_operation_id' => $operation->id,
            'type' => 'income',
            'amount' => 1800,
            'source_type' => 'operation_settlement',
            'squadron_id' => $supportSquadron->id,
            'provenance_locked' => 1,
        ]);

        $this->assertDatabaseHas('ledger_transactions', [
            'operation_settlement_id' => $settlement->id,
            'related_operation_id' => $operation->id,
            'type' => 'income',
            'amount' => 600,
            'source_type' => 'operation_settlement',
            'is_org_owned' => 1,
            'provenance_locked' => 1,
        ]);

        $this->assertDatabaseHas('ledger_inventory_items', [
            'operation_settlement_id' => $settlement->id,
            'related_operation_id' => $operation->id,
            'source_type' => 'commodity',
            'uex_reference_id' => 9001,
            'quantity' => 8,
            'provenance_locked' => 1,
        ]);

        $this->assertDatabaseHas('ledger_inventory_items', [
            'operation_settlement_id' => $settlement->id,
            'related_operation_id' => $operation->id,
            'source_type' => 'component',
            'uex_reference_id' => 9101,
            'quantity' => 2,
            'squadron_id' => $supportSquadron->id,
            'provenance_locked' => 1,
        ]);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->put(route('operations.aar.update', $operation), [
                'after_action_report' => 'Updated text still works',
                'attendance_user_ids' => [],
                'no_show_user_ids' => [],
            ])
            ->assertSessionHasErrors('attendance_user_ids');

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->put(route('operations.aar.update', $operation), [
                'after_action_report' => 'Updated text still works',
                'attendance_user_ids' => [$participant->id],
                'no_show_user_ids' => [],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'After Action Report updated.');

        $operation->refresh();
        $this->assertSame('Updated text still works', $operation->after_action_report);
        $this->assertSame([$participant->id], $operation->after_action_attendance_user_ids);
    }

    public function test_reopening_operation_settlement_removes_generated_receipts_and_unlocks_attendance(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser();
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)->post(route('operations.settlement.finalize', $operation), [
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 4200,
                ],
            ],
            'loot_rows' => [
                [
                    'row_key' => 'loot-member',
                    'source_type' => 'item',
                    'uex_reference_id' => 9101,
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $settlement = OperationSettlement::query()->where('operation_id', $operation->id)->firstOrFail();

        $this->assertDatabaseHas('ledger_transactions', [
            'operation_settlement_id' => $settlement->id,
        ]);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.reopen', $operation))
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'Operation settlement reopened.');

        $settlement->refresh();

        $this->assertNull($settlement->finalized_at);
        $this->assertNotNull($settlement->reopened_at);

        $this->assertDatabaseMissing('ledger_transactions', [
            'operation_settlement_id' => $settlement->id,
        ]);

        $this->assertDatabaseMissing('ledger_inventory_items', [
            'operation_settlement_id' => $settlement->id,
        ]);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->put(route('operations.aar.update', $operation), [
                'after_action_report' => 'Attendance unlocked again',
                'attendance_user_ids' => [],
                'no_show_user_ids' => [$participant->id],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'After Action Report updated.');

        $operation->refresh();
        $this->assertSame([], $operation->after_action_attendance_user_ids);
        $this->assertSame([$participant->id], $operation->after_action_no_show_user_ids);
    }

    public function test_reopening_unfinalized_operation_settlement_is_blocked(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser();
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [
                [
                    'row_key' => 'draft-1',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 4200,
                ],
            ],
            'loot_rows' => [],
            'finalized_at' => null,
            'finalized_by_user_id' => null,
        ]);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.reopen', $operation))
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('settlement');
    }

    public function test_reopen_history_is_preserved_after_finalizing_again(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'ReopenPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $payload = [
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 4200,
                    'notes' => 'Crew share',
                ],
            ],
            'loot_rows' => [],
        ];

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), $payload)
            ->assertRedirect('/operations/dashboard');

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.reopen', $operation))
            ->assertRedirect('/operations/dashboard');

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), $payload)
            ->assertRedirect('/operations/dashboard');

        $settlement = OperationSettlement::query()->where('operation_id', $operation->id)->firstOrFail();

        $this->assertNotNull($settlement->reopened_at);

        $this->actingAs($creator)
            ->get('/operations/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/OperationDashboard')
                ->where('afterActionOperations.0.operation_settlement.activity.reopened_by', $creator->rsi_handle ?? $creator->discord_name ?? $creator->name)
            );
    }

    public function test_finalizing_operation_settlement_is_blocked_after_it_is_already_finalized(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'FinalizedPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $payload = [
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 4200,
                ],
            ],
            'loot_rows' => [],
        ];

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), $payload)
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'Operation settlement finalized.');

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), $payload)
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('settlement');
    }

    public function test_reopening_is_blocked_after_settlement_loot_is_transferred(): void
    {
        config()->set('services.ledger.enabled', true);

        $creator = $this->directorUser();
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [],
                'loot_rows' => [
                    [
                        'row_key' => 'loot-member',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => 'member',
                        'recipient_user_id' => $creator->id,
                        'quantity' => 8,
                        'unit_label' => 'SCU',
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard');

        $settlementItem = LedgerInventoryItem::query()
            ->where('operation_settlement_id', OperationSettlement::query()->where('operation_id', $operation->id)->value('id'))
            ->firstOrFail();

        $this->actingAs($creator)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $settlementItem->id,
                'destination_type' => 'organization',
                'quantity' => 2,
                'notes' => 'Move recovered cargo into Horizon stock',
            ])
            ->assertRedirect();

        $transferRequest = \App\Models\LedgerTransferRequest::query()
            ->where('transfer_kind', 'inventory')
            ->firstOrFail();

        $this->actingAs($creator)
            ->post(route('organization.ledger.inventory.transfer.approve', $transferRequest->id))
            ->assertRedirect();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.reopen', $operation))
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('settlement');
    }

    public function test_reopening_is_blocked_after_recipient_ledger_starts_fund_transfers(): void
    {
        config()->set('services.ledger.enabled', true);

        $creator = $this->directorUser();
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'member-pay',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $creator->id,
                        'amount' => 6000,
                        'notes' => 'Operation cut',
                    ],
                ],
                'loot_rows' => [],
            ])
            ->assertRedirect('/operations/dashboard');

        $this->actingAs($creator)
            ->post(route('ledger.transactions.transfer'), [
                'destination_type' => 'organization',
                'amount' => 1000,
                'description' => 'Move part of the payout into Horizon',
                'transaction_date' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.reopen', $operation))
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('settlement');
    }

    public function test_reopening_mixed_finalized_settlement_removes_all_generated_receipts_when_safe(): void
    {
        config()->set('services.ledger.enabled', true);

        $creator = $this->directorUser();
        $squadron = $this->activeSquadron($creator, 'Aurora Wing');
        $operation = $this->completedOperation($creator, $squadron, [
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'member-pay',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $creator->id,
                        'amount' => 2000,
                    ],
                    [
                        'row_key' => 'squadron-pay',
                        'recipient_type' => 'squadron',
                        'recipient_squadron_id' => $squadron->id,
                        'amount' => 3000,
                    ],
                    [
                        'row_key' => 'org-pay',
                        'recipient_type' => 'organization',
                        'amount' => 4000,
                    ],
                ],
                'loot_rows' => [
                    [
                        'row_key' => 'member-loot',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => 'member',
                        'recipient_user_id' => $creator->id,
                        'quantity' => 4,
                        'unit_label' => 'SCU',
                    ],
                    [
                        'row_key' => 'squadron-loot',
                        'source_type' => 'item',
                        'uex_reference_id' => 9101,
                        'recipient_type' => 'squadron',
                        'recipient_squadron_id' => $squadron->id,
                        'quantity' => 1,
                        'unit_label' => 'units',
                    ],
                    [
                        'row_key' => 'org-loot',
                        'source_type' => 'component',
                        'uex_reference_id' => 9101,
                        'recipient_type' => 'organization',
                        'quantity' => 2,
                        'unit_label' => 'units',
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'Operation settlement finalized.');

        $settlement = OperationSettlement::query()->where('operation_id', $operation->id)->firstOrFail();

        $this->assertDatabaseHas('ledger_inventory_items', [
            'operation_settlement_id' => $settlement->id,
            'user_id' => $creator->id,
            'squadron_id' => null,
            'is_org_owned' => 0,
            'source_type' => 'commodity',
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('ledger_inventory_items', [
            'operation_settlement_id' => $settlement->id,
            'user_id' => $creator->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => 0,
            'source_type' => 'item',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('ledger_inventory_items', [
            'operation_settlement_id' => $settlement->id,
            'user_id' => $creator->id,
            'squadron_id' => null,
            'is_org_owned' => 1,
            'source_type' => 'component',
            'quantity' => 2,
        ]);

        $transactionIds = LedgerTransaction::query()
            ->where('operation_settlement_id', $settlement->id)
            ->pluck('id')
            ->all();
        $itemIds = LedgerInventoryItem::query()
            ->where('operation_settlement_id', $settlement->id)
            ->pluck('id')
            ->all();

        $this->assertCount(3, $transactionIds);
        $this->assertNotEmpty($itemIds);

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.reopen', $operation))
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('success', 'Operation settlement reopened.');

        $settlement->refresh();

        $this->assertNull($settlement->finalized_at);
        $this->assertNull($settlement->finalized_by_user_id);
        $this->assertSame($creator->id, $settlement->reopened_by_user_id);
        $this->assertNotNull($settlement->reopened_at);

        foreach ($transactionIds as $transactionId) {
            $this->assertDatabaseMissing('ledger_transactions', ['id' => $transactionId]);
        }

        foreach ($itemIds as $itemId) {
            $this->assertDatabaseMissing('ledger_inventory_items', ['id' => $itemId]);
        }
    }

    public function test_reopening_mixed_finalized_settlement_is_blocked_after_any_generated_loot_receipt_is_transferred(): void
    {
        config()->set('services.ledger.enabled', true);

        $creator = $this->directorUser();
        $squadron = $this->activeSquadron($creator, 'Aurora Wing');
        $operation = $this->completedOperation($creator, $squadron, [
            'squadron_name' => $squadron->name,
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'member-pay',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $creator->id,
                        'amount' => 2000,
                    ],
                    [
                        'row_key' => 'squadron-pay',
                        'recipient_type' => 'squadron',
                        'recipient_squadron_id' => $squadron->id,
                        'amount' => 3000,
                    ],
                    [
                        'row_key' => 'org-pay',
                        'recipient_type' => 'organization',
                        'amount' => 4000,
                    ],
                ],
                'loot_rows' => [
                    [
                        'row_key' => 'member-loot',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => 'member',
                        'recipient_user_id' => $creator->id,
                        'quantity' => 4,
                        'unit_label' => 'SCU',
                    ],
                    [
                        'row_key' => 'squadron-loot',
                        'source_type' => 'item',
                        'uex_reference_id' => 9101,
                        'recipient_type' => 'squadron',
                        'recipient_squadron_id' => $squadron->id,
                        'quantity' => 1,
                        'unit_label' => 'units',
                    ],
                    [
                        'row_key' => 'org-loot',
                        'source_type' => 'component',
                        'uex_reference_id' => 9101,
                        'recipient_type' => 'organization',
                        'quantity' => 2,
                        'unit_label' => 'units',
                    ],
                ],
            ])
            ->assertRedirect('/operations/dashboard');

        $settlementId = OperationSettlement::query()->where('operation_id', $operation->id)->value('id');

        $settlementItem = LedgerInventoryItem::query()
            ->where('operation_settlement_id', $settlementId)
            ->where('user_id', $creator->id)
            ->where('is_org_owned', false)
            ->whereNull('squadron_id')
            ->firstOrFail();

        $this->actingAs($creator)
            ->post(route('ledger.inventory.transfer'), [
                'inventory_item_id' => $settlementItem->id,
                'destination_type' => 'organization',
                'quantity' => 2,
                'notes' => 'Move mixed settlement cargo into Horizon stock',
            ])
            ->assertRedirect();

        $transferRequest = \App\Models\LedgerTransferRequest::query()
            ->where('transfer_kind', 'inventory')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($creator)
            ->post(route('organization.ledger.inventory.transfer.approve', $transferRequest->id))
            ->assertRedirect();

        $this->actingAs($creator)
            ->from('/operations/dashboard')
            ->post(route('operations.settlement.reopen', $operation))
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHasErrors('settlement');
    }

    public function test_guest_json_settlement_update_request_returns_unauthenticated_error(): void
    {
        $creator = $this->directorUser();
        $operation = $this->completedOperation($creator);

        $this->putJson(route('operations.settlement.update', $operation), [
            'money_rows' => [],
            'loot_rows' => [],
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_non_manager_cannot_manage_operation_settlement_over_json(): void
    {
        $creator = $this->directorUser();
        $viewer = $this->verifiedUser([
            'rank' => 'lieutenant',
            'rank_level' => 2,
        ]);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $creator->id,
            'finalized_at' => now(),
        ]);

        $payload = [
            'money_rows' => [],
            'loot_rows' => [],
        ];

        $this->actingAs($viewer)
            ->putJson(route('operations.settlement.update', $operation), $payload)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('operations.settlement.finalize', $operation), $payload)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('operations.settlement.reopen', $operation))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson(route('operations.settlement.export', $operation))
            ->assertForbidden();
    }

    public function test_settlement_update_json_validation_errors_use_standard_error_shape(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'JsonDraftPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $response = $this->actingAs($creator)
            ->putJson(route('operations.settlement.update', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'money-1',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 1250.5,
                    ],
                ],
                'loot_rows' => [],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $errors = $response->json('errors');

        $this->assertSame('The money_rows.0.amount field must be an integer.', $errors['money_rows.0.amount'][0] ?? null);
    }

    public function test_settlement_update_json_success_returns_operation_payload_with_saved_draft(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'JsonSavePilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $response = $this->actingAs($creator)
            ->putJson(route('operations.settlement.update', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'money-1',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 6400,
                        'notes' => 'Json payout draft',
                    ],
                ],
                'loot_rows' => [],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.operation_settlement.money_rows.0.amount', 6400)
            ->assertJsonPath('payload.operation.operation_settlement.money_rows.0.notes', 'Json payout draft')
            ->assertJsonPath('payload.operation.operation_settlement.is_finalized', false);
    }

    public function test_settlement_finalize_json_rejects_unresolved_rows_with_standard_error_shape(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'JsonFinalizePilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $response = $this->actingAs($creator)
            ->postJson(route('operations.settlement.finalize', $operation), [
                'money_rows' => [],
                'loot_rows' => [
                    [
                        'row_key' => 'loot-review',
                        'source_type' => 'commodity',
                        'uex_reference_id' => 9001,
                        'recipient_type' => '',
                        'quantity' => 4,
                    ],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $errors = $response->json('errors');

        $this->assertSame(
            'Each loot row needs a destination before the settlement can be finalized.',
            $errors['loot_rows'][0] ?? null
        );
    }

    public function test_settlement_finalize_and_reopen_json_requests_return_operation_payloads(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'JsonRoundTripPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $finalizeResponse = $this->actingAs($creator)
            ->postJson(route('operations.settlement.finalize', $operation), [
                'money_rows' => [
                    [
                        'row_key' => 'money-1',
                        'recipient_type' => 'member',
                        'recipient_user_id' => $participant->id,
                        'amount' => 7200,
                    ],
                ],
                'loot_rows' => [],
            ]);

        $finalizeResponse
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.operation_settlement.is_finalized', true);

        $reopenResponse = $this->actingAs($creator)
            ->postJson(route('operations.settlement.reopen', $operation));

        $reopenResponse
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('payload.operation.id', $operation->id)
            ->assertJsonPath('payload.operation.operation_settlement.is_finalized', false);

        $this->assertNotNull(
            OperationSettlement::query()->where('operation_id', $operation->id)->firstOrFail()->reopened_at
        );
    }

    public function test_settlement_export_json_conflict_uses_standard_error_shape_until_finalized(): void
    {
        $creator = $this->directorUser();
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        $this->actingAs($creator)
            ->getJson(route('operations.settlement.export', $operation))
            ->assertStatus(409)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Finalize the operation settlement before exporting it.');
    }

    public function test_non_manager_cannot_save_finalize_or_reopen_operation_settlement(): void
    {
        $creator = $this->verifiedUser([
            'rank' => 'cit',
            'rank_level' => 3,
        ]);
        $otherOfficer = $this->verifiedUser([
            'rank' => 'lieutenant',
            'rank_level' => 2,
        ]);
        $participant = $this->verifiedUser();
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $payload = [
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 1000,
                ],
            ],
            'loot_rows' => [],
        ];

        $this->actingAs($otherOfficer)
            ->put(route('operations.settlement.update', $operation), $payload)
            ->assertForbidden();

        $this->actingAs($otherOfficer)
            ->post(route('operations.settlement.finalize', $operation), $payload)
            ->assertForbidden();

        OperationSettlement::query()->create([
            'operation_id' => $operation->id,
            'money_rows' => [],
            'loot_rows' => [],
            'finalized_by_user_id' => $creator->id,
            'finalized_at' => now(),
        ]);

        $this->actingAs($otherOfficer)
            ->post(route('operations.settlement.reopen', $operation))
            ->assertForbidden();
    }

    public function test_operation_settlement_receipts_are_blocked_from_normal_ledger_edit_and_delete_flows(): void
    {
        $creator = $this->directorUser();
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$creator->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)->post(route('operations.settlement.finalize', $operation), [
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $creator->id,
                    'amount' => 3500,
                ],
            ],
            'loot_rows' => [
                [
                    'row_key' => 'loot-member',
                    'source_type' => 'item',
                    'uex_reference_id' => 9101,
                    'recipient_type' => 'member',
                    'recipient_user_id' => $creator->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $transaction = LedgerTransaction::query()
            ->where('user_id', $creator->id)
            ->where('source_type', 'operation_settlement')
            ->firstOrFail();
        $item = LedgerInventoryItem::query()
            ->where('user_id', $creator->id)
            ->where('operation_settlement_id', $transaction->operation_settlement_id)
            ->firstOrFail();

        $this->actingAs($creator)
            ->from('/ledger')
            ->put(route('ledger.transactions.update', $transaction), [
                'type' => 'income',
                'amount' => 9999,
                'description' => 'Should stay locked',
            ])
            ->assertSessionHasErrors('transaction');

        $this->actingAs($creator)
            ->from('/ledger')
            ->delete(route('ledger.transactions.destroy', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->actingAs($creator)
            ->from('/ledger')
            ->put(route('ledger.inventory.update', $item), [
                'source_type' => 'item',
                'uex_reference_type' => 'item',
                'uex_reference_id' => 9101,
                'quantity' => 3,
            ])
            ->assertSessionHasErrors('inventory');

        $this->actingAs($creator)
            ->from('/ledger')
            ->delete(route('ledger.inventory.destroy', $item))
            ->assertSessionHasErrors('inventory');
    }

    public function test_finalized_operation_settlement_can_be_exported_as_csv(): void
    {
        $creator = $this->directorUser();
        $participant = $this->verifiedUser(['rsi_handle' => 'ExportPilot']);
        $operation = $this->completedOperation($creator, null, [
            'after_action_attendance_user_ids' => [$participant->id],
        ]);

        $this->seedUexSettlementReferences();

        $this->actingAs($creator)->post(route('operations.settlement.finalize', $operation), [
            'money_rows' => [
                [
                    'row_key' => 'member-pay',
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'amount' => 5000,
                    'notes' => 'Export payout',
                ],
            ],
            'loot_rows' => [
                [
                    'row_key' => 'loot-member',
                    'source_type' => 'commodity',
                    'uex_reference_id' => 9001,
                    'recipient_type' => 'member',
                    'recipient_user_id' => $participant->id,
                    'quantity' => 4,
                    'unit_label' => 'SCU',
                    'notes' => 'Export cargo',
                ],
            ],
        ]);

        $response = $this->actingAs($creator)
            ->get(route('operations.settlement.export', $operation));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('section,operation_id,operation_title,recipient', $content);
        $this->assertStringContainsString('money,' . $operation->id, $content);
        $this->assertStringContainsString('loot,' . $operation->id, $content);
        $this->assertStringContainsString('Export payout', $content);
        $this->assertStringContainsString('Agricium', $content);
    }

    private function seedUexSettlementReferences(): void
    {
        DB::table('uex_commodities')->insert([
            'uex_id' => 9001,
            'name' => 'Agricium',
            'source_payload' => json_encode(['name' => 'Agricium']),
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('uex_items')->insert([
            'uex_id' => 9101,
            'name' => 'Atlas Drive',
            'type' => 'Quantum Drive',
            'source_payload' => json_encode(['name' => 'Atlas Drive']),
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function activeSquadron(User $leader, string $name): Squadron
    {
        return Squadron::query()->create([
            'name' => $name,
            'slug' => str($name)->slug('-')->value() . '-' . strtolower((string) str()->random(4)),
            'status' => 'active',
            'leader_id' => $leader->id,
        ]);
    }

    private function verifiedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ], $attributes));
    }

    private function directorUser(array $attributes = []): User
    {
        $directorRole = Role::create([
            'name' => 'Director',
            'slug' => 'director',
            'description' => 'Test director role',
            'is_system' => true,
        ]);

        $user = $this->verifiedUser(array_merge([
            'rank' => 'admiral',
            'rank_level' => 5,
        ], $attributes));

        $user->roles()->attach($directorRole->id);
        $user->load('roles');

        return $user;
    }

    private function completedOperation(User $creator, ?Squadron $squadron = null, array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $creator->id,
            'squadron_id' => $squadron?->id,
            'squadron_name' => $squadron?->name,
            'title' => 'Post Op Review',
            'description' => 'Completed operation.',
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => 'completed',
            'completion_outcome' => 'success',
            'after_action_attendance_user_ids' => [],
            'after_action_no_show_user_ids' => [],
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }
}
