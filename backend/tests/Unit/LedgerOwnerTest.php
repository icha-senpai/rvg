<?php

namespace Tests\Unit;

use App\Models\LedgerInventoryItem;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;
use App\Services\LedgerCycleService;
use App\Services\OrgLedgerOwner;
use App\Services\PersonalLedgerOwner;
use App\Services\SquadronLedgerOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_owner_stamps_personal_scope_and_resolves_default_account(): void
    {
        $user = User::factory()->create();

        $owner = new PersonalLedgerOwner($user, app(LedgerCycleService::class));
        $account = $owner->resolveAccount(null);

        $this->assertSame([
            'user_id' => $user->id,
            'squadron_id' => null,
            'is_org_owned' => false,
        ], $owner->applyOwnership([]));
        $this->assertSame($user->id, $account->user_id);
        $this->assertFalse((bool) $account->is_org_owned);
    }

    public function test_squadron_owner_stamps_squadron_scope_and_resolves_default_account(): void
    {
        $actor = User::factory()->create();
        $squadron = Squadron::query()->create([
            'name' => 'Ledger Spears',
            'slug' => 'ledger-spears',
            'status' => 'active',
            'branch' => 'operations',
            'division' => 'alpha',
            'leader_id' => $actor->id,
            'recruiting' => true,
        ]);

        $owner = new SquadronLedgerOwner($squadron, $actor, app(LedgerCycleService::class));
        $account = $owner->resolveAccount(null);

        $this->assertSame([
            'user_id' => $actor->id,
            'squadron_id' => $squadron->id,
            'is_org_owned' => false,
        ], $owner->applyOwnership([]));
        $this->assertSame($squadron->id, $account->squadron_id);
        $this->assertFalse((bool) $account->is_org_owned);
    }

    public function test_org_owner_stamps_org_scope_and_resolves_default_account(): void
    {
        $actor = User::factory()->create();

        $owner = new OrgLedgerOwner($actor, app(LedgerCycleService::class));
        $account = $owner->resolveAccount(null);

        $this->assertSame([
            'user_id' => $actor->id,
            'squadron_id' => null,
            'is_org_owned' => true,
        ], $owner->applyOwnership([]));
        $this->assertTrue((bool) $account->is_org_owned);
        $this->assertNull($account->squadron_id);
    }

    public function test_owner_scope_assertions_reject_foreign_records(): void
    {
        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDay(),
            'is_current' => true,
        ]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $transaction = LedgerTransaction::query()->create([
            'user_id' => $other->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'ledger_account_id' => (new PersonalLedgerOwner($other, app(LedgerCycleService::class)))->resolveAccount(null)->id,
            'wipe_cycle_id' => $wipe->id,
            'type' => 'income',
            'amount' => 100,
            'currency' => 'aUEC',
            'description' => 'Other member record',
            'transaction_date' => now(),
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new PersonalLedgerOwner($user, app(LedgerCycleService::class)))->assertOwns($transaction);
    }

    public function test_locked_inventory_stays_immutable_for_owner_assertions(): void
    {
        $wipe = WipeCycle::query()->create([
            'name' => '4.1 Live',
            'star_citizen_version' => '4.1',
            'wipe_type' => 'inventory',
            'started_at' => now()->subDay(),
            'is_current' => true,
        ]);
        $user = User::factory()->create();

        $item = LedgerInventoryItem::query()->create([
            'user_id' => $user->id,
            'squadron_id' => null,
            'is_org_owned' => false,
            'wipe_cycle_id' => $wipe->id,
            'source_type' => 'custom',
            'custom_name' => 'Locked crate',
            'quantity' => 1,
            'provenance_locked' => true,
            'acquired_at' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        (new PersonalLedgerOwner($user, app(LedgerCycleService::class)))->assertOwns($item);
    }
}
