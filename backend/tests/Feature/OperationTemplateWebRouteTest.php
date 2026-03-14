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
                    'slots' => ['Pilot', 'Gunner'],
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
    }
}
