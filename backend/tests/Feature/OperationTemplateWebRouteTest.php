<?php

namespace Tests\Feature;

use App\Models\OperationTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationTemplateWebRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_lieutenant_can_create_a_personal_operation_template_via_web_route(): void
    {
        $role = Role::create([
            'name' => 'Lieutenant',
            'slug' => 'lieutenant',
            'description' => 'Test lieutenant role',
            'is_system' => true,
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank_level' => 2,
        ]);

        $user->roles()->attach($role->id);
        $user->load('roles');

        $response = $this
            ->actingAs($user)
            ->from('/operations/dashboard')
            ->post('/operation-templates', [
                'name' => 'Escort Template',
                'scope' => OperationTemplate::SCOPE_PERSONAL,
                'payload' => [
                    'title' => 'Convoy Escort',
                    'gameplay_type' => 'escort',
                    'description' => 'Protect the convoy.',
                    'notes' => 'Detailed briefing text.',
                    'visibility' => 'open',
                    'operation_type' => 'operation',
                    'branch' => 'defence',
                    'operation_strictness' => 'normal',
                    'start_location' => 'Everus Harbor',
                    'operation_location' => 'Hurston',
                    'roles' => [
                        [
                            'role_display_name' => 'Pilot',
                            'capacity' => 2,
                            'sort_order' => 7,
                            'is_required' => true,
                        ],
                        [
                            'role_display_name' => 'Gunner',
                            'capacity' => 1,
                            'sort_order' => 11,
                            'is_required' => false,
                        ],
                    ],
                ],
            ]);

        $response
            ->assertRedirect('/operations/dashboard')
            ->assertSessionHas('operationTemplate', function (array $flash): bool {
                return $flash['event'] === 'created' && ! empty($flash['id']);
            });

        $template = OperationTemplate::query()->first();

        $this->assertNotNull($template);
        $this->assertSame('Escort Template', $template->name);
        $this->assertSame(OperationTemplate::SCOPE_PERSONAL, $template->scope);
        $this->assertSame($user->id, $template->owner_user_id);
        $this->assertSame($user->id, $template->created_by);
        $this->assertSame('escort', $template->payload['gameplay_type']);
        $this->assertSame('Detailed briefing text.', $template->payload['extended_description']);
        $this->assertSame(['Pilot', 'Gunner'], $template->payload['slots']);
        $this->assertSame(7, $template->payload['roles'][0]['sort_order']);
        $this->assertTrue($template->payload['roles'][0]['is_required']);
        $this->assertSame(11, $template->payload['roles'][1]['sort_order']);
        $this->assertFalse($template->payload['roles'][1]['is_required']);
    }

    public function test_non_lieutenant_cannot_create_operation_template_via_web_route(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank_level' => 1,
        ]);

        $this->actingAs($user)
            ->post('/operation-templates', [
                'name' => 'Blocked Template',
                'scope' => OperationTemplate::SCOPE_PERSONAL,
                'payload' => [
                    'title' => 'Blocked',
                    'gameplay_type' => 'escort',
                ],
            ])
            ->assertForbidden();
    }

    public function test_non_owner_cannot_update_or_delete_personal_operation_template_via_web_routes(): void
    {
        $role = Role::create([
            'name' => 'Lieutenant',
            'slug' => 'lieutenant',
            'description' => 'Test lieutenant role',
            'is_system' => true,
        ]);

        /** @var User $owner */
        $owner = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank_level' => 2,
        ]);
        $owner->roles()->attach($role->id);
        $owner->load('roles');

        /** @var User $otherOfficer */
        $otherOfficer = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
            'rank_level' => 2,
        ]);
        $otherOfficer->roles()->attach($role->id);
        $otherOfficer->load('roles');

        $template = OperationTemplate::query()->create([
            'name' => 'Owner Template',
            'scope' => OperationTemplate::SCOPE_PERSONAL,
            'owner_user_id' => $owner->id,
            'created_by' => $owner->id,
            'payload' => [
                'title' => 'Owner Template',
                'gameplay_type' => 'escort',
                'roles' => [],
            ],
        ]);

        $this->actingAs($otherOfficer)
            ->put('/operation-templates/' . $template->id, [
                'name' => 'Stolen Template',
                'payload' => [
                    'title' => 'Still Owner Template',
                    'gameplay_type' => 'escort',
                    'roles' => [],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($otherOfficer)
            ->delete('/operation-templates/' . $template->id)
            ->assertForbidden();
    }
}
