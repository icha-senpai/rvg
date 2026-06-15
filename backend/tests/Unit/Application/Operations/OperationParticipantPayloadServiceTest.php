<?php

namespace Tests\Unit\Application\Operations;

use App\Application\Operations\OperationParticipantPayloadService;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationParticipantPayloadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_hides_assignments_when_viewer_cannot_see_them(): void
    {
        $service = app(OperationParticipantPayloadService::class);
        $participant = $this->participant();

        $payload = $service->participant($participant, false);

        $this->assertSame($participant->id, $payload['id']);
        $this->assertNull($payload['slot']);
        $this->assertNull($payload['role']);
        $this->assertNull($payload['attendance_status']);
        $this->assertNull($payload['notes']);
        $this->assertSame($participant->runtime_status, $payload['runtime_status']);
        $this->assertSame($participant->runtime_source, $payload['runtime_source']);
        $this->assertSame($participant->user->id, $payload['user']['id']);
    }

    public function test_participant_includes_assignments_and_runtime_fields_when_allowed(): void
    {
        $service = app(OperationParticipantPayloadService::class);
        $participant = $this->participant();

        $payload = $service->participant($participant, true);

        $this->assertSame('Pilot', $payload['slot']);
        $this->assertSame('signed_up', $payload['attendance_status']);
        $this->assertSame('Bring medpens', $payload['notes']);
        $this->assertSame('Need recovery if server desync hits', $payload['runtime_notes']);
        $this->assertSame(125000, $payload['starting_auec']);
        $this->assertSame(171000, $payload['ending_auec']);
        $this->assertSame('pilot', $payload['role']['role_name']);
        $this->assertSame('Pilot', $payload['role']['role_display_name']);
        $this->assertSame(2, $payload['role']['capacity']);
        $this->assertNotNull($payload['synced_in_at']);
        $this->assertSame($participant->user->rsi_handle, $payload['user']['rsi_handle']);
    }

    public function test_participants_maps_operation_rows_in_order(): void
    {
        $service = app(OperationParticipantPayloadService::class);
        $operation = $this->operation();

        $first = $this->participant($operation, 'Pilot');
        $second = $this->participant($operation, 'Medic');

        $operation->load('participants.user', 'participants.role');
        $payloads = $service->participants($operation, true);

        $this->assertCount(2, $payloads);
        $this->assertSame([$first->id, $second->id], $payloads->pluck('id')->all());
        $this->assertSame(['Pilot', 'Medic'], $payloads->pluck('slot')->all());
    }

    private function operation(): Operation
    {
        $operation = new Operation();
        $operation->forceFill([
            'created_by' => User::factory()->create()->id,
            'title' => 'Participant payload test',
            'description' => 'Payload operation.',
            'status' => 'published',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
        ]);
        $operation->save();

        return $operation->fresh();
    }

    private function participant(?Operation $operation = null, string $slot = 'Pilot'): OperationParticipant
    {
        $operation ??= $this->operation();
        $user = User::factory()->create([
            'rsi_handle' => $slot . 'User',
            'discord_name' => $slot . 'Discord',
        ]);
        $role = OperationRole::query()->create([
            'operation_id' => $operation->id,
            'role_name' => strtolower($slot),
            'role_display_name' => $slot,
            'capacity' => 2,
        ]);

        return OperationParticipant::query()->create([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'operation_role_id' => $role->id,
            'slot' => $slot,
            'attendance_status' => 'signed_up',
            'runtime_status' => OperationParticipant::RUNTIME_STATUS_SIGNED_UP,
            'runtime_source' => OperationParticipant::RUNTIME_SOURCE_SIGNED_UP,
            'synced_in_at' => now()->subMinutes(10),
            'starting_auec' => 125000,
            'ending_auec' => 171000,
            'runtime_notes' => 'Need recovery if server desync hits',
            'notes' => 'Bring medpens',
        ])->fresh(['user', 'role']);
    }
}
