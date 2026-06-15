<?php

namespace Tests\Unit\Application\Operations\Presenters;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationRole;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_truncates_description_and_maps_selected_squadrons_in_original_order(): void
    {
        $alpha = $this->squadron('Alpha Wing');
        $bravo = $this->squadron('Bravo Wing');
        $operation = $this->operation([
            'description' => str_repeat('A', 141),
            'squadron_name' => 'Bravo Wing, Missing Wing, Alpha Wing',
        ]);

        $summary = OperationPresenter::make($operation->fresh())->summary();

        $this->assertSame(str_repeat('A', 140) . '…', $summary['description']);
        $this->assertSame(
            [$bravo->id, null, $alpha->id],
            collect($summary['selected_squadrons'])->pluck('id')->all()
        );
        $this->assertSame(
            ['Bravo Wing', 'Missing Wing', 'Alpha Wing'],
            collect($summary['selected_squadrons'])->pluck('name')->all()
        );
    }

    public function test_full_calculates_role_fill_counts_and_full_flags(): void
    {
        $operation = $this->operation();
        $leaderRole = OperationRole::query()->create([
            'operation_id' => $operation->id,
            'role_name' => 'leader',
            'role_display_name' => 'Leader',
            'capacity' => 1,
        ]);
        $medicRole = OperationRole::query()->create([
            'operation_id' => $operation->id,
            'role_name' => 'medic',
            'role_display_name' => 'Medic',
            'capacity' => 2,
        ]);

        $this->participant($operation, $leaderRole, 'LeaderPilot', 'Leader');
        $this->participant($operation, $medicRole, 'MedicPilot', 'Medic');

        $payload = OperationPresenter::make($operation->fresh([
            'creator.roles',
            'squadron',
            'participants.user',
            'participants.role',
            'roles.participants.user',
            'images',
        ]))->full();

        $rolesByName = collect($payload['roles'])->keyBy('role_display_name');

        $this->assertSame(1, $rolesByName['Leader']['filled_count']);
        $this->assertSame(0, $rolesByName['Leader']['remaining_spots']);
        $this->assertTrue($rolesByName['Leader']['is_full']);
        $this->assertSame(1, $rolesByName['Medic']['filled_count']);
        $this->assertSame(1, $rolesByName['Medic']['remaining_spots']);
        $this->assertFalse($rolesByName['Medic']['is_full']);
        $this->assertSame('LeaderPilot', $payload['participants'][0]['user']['rsi_handle']);
    }

    public function test_form_falls_back_to_slots_when_role_records_do_not_exist(): void
    {
        $operation = $this->operation([
            'slots' => ['Hammerhead', 'Cutlass Red'],
        ]);

        $payload = OperationPresenter::make($operation->fresh(['images', 'roles']))->form();

        $this->assertSame(['Hammerhead', 'Cutlass Red'], $payload['slots']);
        $this->assertSame(
            ['Hammerhead', 'Cutlass Red'],
            collect($payload['roles'])->pluck('role_display_name')->all()
        );
        $this->assertSame([null, null], collect($payload['roles'])->pluck('id')->all());
    }

    private function operation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => $this->member()->id,
            'title' => 'Presenter test',
            'description' => 'Presenter operation.',
            'status' => 'published',
            'visibility' => 'open',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
            'slots' => [],
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }

    private function participant(Operation $operation, OperationRole $role, string $handle, string $slot): OperationParticipant
    {
        return OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $this->member(['rsi_handle' => $handle])->id,
            'operation_role_id' => $role->id,
            'slot' => $slot,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
        ]);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ], $attributes));
    }

    private function squadron(string $name): Squadron
    {
        return Squadron::query()->create([
            'name' => $name,
            'slug' => str($name)->slug('-')->value(),
            'status' => 'active',
            'leader_id' => $this->member()->id,
        ]);
    }
}
