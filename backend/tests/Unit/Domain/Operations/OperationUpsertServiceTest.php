<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\Services\OperationUpsertService;
use App\Models\Operation;
use App\Models\OperationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationUpsertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_normalizes_role_sort_order_required_flags_and_slots(): void
    {
        $service = app(OperationUpsertService::class);

        $prepared = $service->prepare([
            'title' => 'Operation',
            'roles' => [
                [
                    'role_display_name' => 'Medic',
                    'capacity' => '2',
                    'is_required' => true,
                    'sort_order' => '5',
                ],
                [
                    'role_display_name' => 'Pilot',
                    'capacity' => '',
                ],
            ],
        ]);

        $roles = $prepared['data']['roles'];

        $this->assertSame(['Medic', 'Pilot'], $prepared['data']['slots']);
        $this->assertSame(2, $roles[0]['capacity']);
        $this->assertSame(5, $roles[0]['sort_order']);
        $this->assertTrue($roles[0]['is_required']);
        $this->assertNull($roles[1]['capacity']);
        $this->assertSame(1, $roles[1]['sort_order']);
        $this->assertFalse($roles[1]['is_required']);
    }

    public function test_sync_roles_persists_sort_order_and_required_flags_for_created_and_updated_roles(): void
    {
        $service = app(OperationUpsertService::class);
        $operation = $this->operation();

        $existingRole = OperationRole::query()->create([
            'operation_id' => $operation->id,
            'role_name' => 'pilot',
            'role_display_name' => 'Pilot',
            'capacity' => 1,
            'sort_order' => 9,
            'is_required' => false,
        ]);

        $service->syncRoles($operation, [
            'roles' => [
                [
                    'id' => $existingRole->id,
                    'role_name' => 'pilot',
                    'role_display_name' => 'Flight Lead',
                    'capacity' => 2,
                    'sort_order' => 3,
                    'is_required' => true,
                ],
                [
                    'role_display_name' => 'Medic',
                    'capacity' => 1,
                ],
            ],
        ]);

        $existingRole->refresh();
        $createdRole = OperationRole::query()
            ->where('operation_id', $operation->id)
            ->where('role_display_name', 'Medic')
            ->firstOrFail();

        $this->assertSame('Flight Lead', $existingRole->role_display_name);
        $this->assertSame(2, $existingRole->capacity);
        $this->assertSame(3, $existingRole->sort_order);
        $this->assertTrue($existingRole->is_required);
        $this->assertSame(1, $createdRole->sort_order);
        $this->assertFalse($createdRole->is_required);
    }

    private function operation(): Operation
    {
        $operation = new Operation();
        $operation->forceFill([
            'created_by' => User::factory()->create()->id,
            'title' => 'Upsert test',
            'description' => 'Operation upsert coverage.',
            'status' => 'draft',
            'visibility' => 'open',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);
        $operation->save();

        return $operation->fresh();
    }
}
