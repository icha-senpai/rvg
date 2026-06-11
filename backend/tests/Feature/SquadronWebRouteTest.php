<?php

namespace Tests\Feature;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadronWebRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_apply_to_a_squadron_via_the_web_route(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);

        $squadron = Squadron::create([
            'name' => 'Void Rangers',
            'slug' => 'void-rangers',
            'status' => 'active',
            'recruiting' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/squadrons?squadron=' . $squadron->slug)
            ->post('/squadrons/' . $squadron->id . '/join');

        $response
            ->assertRedirect('/squadrons?squadron=' . $squadron->slug)
            ->assertSessionHas('success', 'Applied to squadron successfully.');

        $this->assertDatabaseHas('squadron_members', [
            'user_id' => $user->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_PENDING,
        ]);
    }

    public function test_squadron_leader_membership_can_accept_an_applicant_without_matching_leader_id(): void
    {
        $leader = $this->verifiedUser();
        $applicant = $this->verifiedUser();
        $squadron = $this->squadron();

        SquadronMember::create([
            'user_id' => $leader->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now(),
        ]);

        $pendingMember = SquadronMember::create([
            'user_id' => $applicant->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_PENDING,
            'role' => SquadronMember::ROLE_MEMBER,
        ]);

        $response = $this
            ->actingAs($leader)
            ->post('/squadrons/' . $squadron->id . '/members/update', [
                'id' => $pendingMember->id,
                'membership_status' => SquadronMember::STATUS_ACTIVE,
                'role' => SquadronMember::ROLE_MEMBER,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success', 'Member updated.');

        $this->assertDatabaseHas('squadron_members', [
            'id' => $pendingMember->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_MEMBER,
        ]);
    }

    public function test_director_like_user_can_reject_an_applicant(): void
    {
        $director = $this->verifiedUser();
        $this->attachRole($director, 'director');

        $applicant = $this->verifiedUser();
        $squadron = $this->squadron();

        $pendingMember = SquadronMember::create([
            'user_id' => $applicant->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_PENDING,
            'role' => SquadronMember::ROLE_MEMBER,
        ]);

        $response = $this
            ->actingAs($director)
            ->post('/squadrons/' . $squadron->id . '/members/remove', [
                'id' => $pendingMember->id,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success', 'Member removed.');

        $this->assertDatabaseMissing('squadron_members', [
            'id' => $pendingMember->id,
        ]);
    }

    public function test_squadron_leader_membership_can_promote_and_demote_lieutenants_without_matching_leader_id(): void
    {
        $leader = $this->verifiedUser();
        $member = $this->verifiedUser();
        $squadron = $this->squadron();

        SquadronMember::create([
            'user_id' => $leader->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_LEADER,
            'joined_at' => now(),
        ]);

        SquadronMember::create([
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        $promoteResponse = $this
            ->actingAs($leader)
            ->post('/squadrons/' . $squadron->id . '/promote-lieutenant', [
                'user_id' => $member->id,
            ]);

        $promoteResponse
            ->assertRedirect()
            ->assertSessionHas('success', 'Lieutenant promoted successfully.');

        $this->assertDatabaseHas('squadron_members', [
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'role' => SquadronMember::ROLE_LIEUTENANT,
        ]);

        $demoteResponse = $this
            ->actingAs($leader)
            ->post('/squadrons/' . $squadron->id . '/demote-lieutenant', [
                'user_id' => $member->id,
            ]);

        $demoteResponse
            ->assertRedirect()
            ->assertSessionHas('success', 'Lieutenant demoted successfully.');

        $this->assertDatabaseHas('squadron_members', [
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'role' => SquadronMember::ROLE_MEMBER,
        ]);
    }

    public function test_director_like_user_can_promote_and_demote_lieutenants(): void
    {
        $director = $this->verifiedUser();
        $this->attachRole($director, 'tech_director');

        $member = $this->verifiedUser();
        $squadron = $this->squadron();

        SquadronMember::create([
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => SquadronMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        $promoteResponse = $this
            ->actingAs($director)
            ->post('/squadrons/' . $squadron->id . '/promote-lieutenant', [
                'user_id' => $member->id,
            ]);

        $promoteResponse
            ->assertRedirect()
            ->assertSessionHas('success', 'Lieutenant promoted successfully.');

        $demoteResponse = $this
            ->actingAs($director)
            ->post('/squadrons/' . $squadron->id . '/demote-lieutenant', [
                'user_id' => $member->id,
            ]);

        $demoteResponse
            ->assertRedirect()
            ->assertSessionHas('success', 'Lieutenant demoted successfully.');

        $this->assertDatabaseHas('squadron_members', [
            'user_id' => $member->id,
            'squadron_id' => $squadron->id,
            'role' => SquadronMember::ROLE_MEMBER,
        ]);
    }

    public function test_commander_cannot_update_a_squadron_they_do_not_lead(): void
    {
        $commander = $this->verifiedUser();
        $commanderRole = $this->attachRole($commander, 'commander');
        $this->attachPermission($commanderRole, 'squadron.manage');

        $squadron = $this->squadron([
            'motto' => 'Original motto',
        ]);

        $response = $this
            ->actingAs($commander)
            ->from('/squadrons?squadron=' . $squadron->slug)
            ->post('/squadrons/' . $squadron->id . '/settings', [
                'motto' => 'Changed motto',
                'recruiting' => true,
            ]);

        $response
            ->assertRedirect('/squadrons?squadron=' . $squadron->slug)
            ->assertSessionHasErrors([
                'squadron' => 'You do not have permission to update this squadron.',
            ]);

        $this->assertDatabaseHas('squadrons', [
            'id' => $squadron->id,
            'motto' => 'Original motto',
        ]);
    }

    protected function verifiedUser(): User
    {
        return User::factory()->create([
            'global_status' => User::STATUS_ACTIVE,
            'rsi_verified_at' => now(),
        ]);
    }

    protected function squadron(array $attributes = []): Squadron
    {
        return Squadron::create(array_merge([
            'name' => 'Void Rangers ' . uniqid(),
            'slug' => 'void-rangers-' . uniqid(),
            'status' => 'active',
            'recruiting' => true,
        ], $attributes));
    }

    protected function attachRole(User $user, string $slug): Role
    {
        $role = Role::create([
            'name' => str($slug)->replace('_', ' ')->title()->toString(),
            'slug' => $slug,
            'is_system' => true,
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $role;
    }

    protected function attachPermission(Role $role, string $slug): Permission
    {
        $permission = Permission::create([
            'name' => str($slug)->replace(['.', '-'], ' ')->title()->toString(),
            'slug' => $slug,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return $permission;
    }
}
