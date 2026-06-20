<?php

namespace App\Services;

use App\Domain\AccessControl\RoleHierarchy;
use App\Domain\Squadrons\Enums\SquadronStatus;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevAuthService
{
    public function enabled(): bool
    {
        return (bool) config('dev_auth.enabled', false)
            && app()->environment(['local', 'testing']);
    }

    public function ensureEnabled(): void
    {
        abort_unless($this->enabled(), 404);
    }

    public function personas(): array
    {
        return [
            'member' => [
                'label' => 'Normal Member',
                'description' => 'Verified baseline member account.',
                'name' => 'Dev Member',
                'discord_name' => 'Dev Member',
                'discord_id' => 'dev-member',
                'rsi_handle' => 'DevMember',
                'roles' => ['member'],
                'verified' => true,
            ],
            'penetrators_lt' => [
                'label' => 'Penetrators Lieutenant',
                'description' => 'Verified lieutenant inside the Penetrators squadron.',
                'name' => 'Penetrators Lieutenant',
                'discord_name' => 'Penetrators Lieutenant',
                'discord_id' => 'dev-penetrators-lt',
                'rsi_handle' => 'PenetratorsLT',
                'roles' => ['lieutenant'],
                'verified' => true,
                'squadron' => [
                    'name' => 'Penetrators',
                    'slug' => 'penetrators',
                    'membership_role' => SquadronMember::ROLE_LIEUTENANT,
                ],
            ],
            'cit' => [
                'label' => 'CIT',
                'description' => 'Verified Commander in Training account.',
                'name' => 'Dev CIT',
                'discord_name' => 'Dev CIT',
                'discord_id' => 'dev-cit',
                'rsi_handle' => 'DevCIT',
                'roles' => ['cit'],
                'verified' => true,
            ],
            'commander' => [
                'label' => 'Commander',
                'description' => 'Verified commander account leading Penetrators.',
                'name' => 'Dev Commander',
                'discord_name' => 'Dev Commander',
                'discord_id' => 'dev-commander',
                'rsi_handle' => 'DevCommander',
                'roles' => ['commander'],
                'verified' => true,
                'squadron' => [
                    'name' => 'Penetrators',
                    'slug' => 'penetrators',
                    'membership_role' => SquadronMember::ROLE_LEADER,
                ],
            ],
            'wing_commander' => [
                'label' => 'Wing Commander',
                'description' => 'Verified wing commander account.',
                'name' => 'Dev Wing Commander',
                'discord_name' => 'Dev Wing Commander',
                'discord_id' => 'dev-wing-commander',
                'rsi_handle' => 'DevWingCommander',
                'roles' => ['wing_commander'],
                'verified' => true,
            ],
            'admiral' => [
                'label' => 'Admiral',
                'description' => 'Verified admiral account.',
                'name' => 'Dev Admiral',
                'discord_name' => 'Dev Admiral',
                'discord_id' => 'dev-admiral',
                'rsi_handle' => 'DevAdmiral',
                'roles' => ['admiral'],
                'verified' => true,
            ],
            'grand_admiral' => [
                'label' => 'Grand Admiral',
                'description' => 'Verified grand admiral account.',
                'name' => 'Dev Grand Admiral',
                'discord_name' => 'Dev Grand Admiral',
                'discord_id' => 'dev-grand-admiral',
                'rsi_handle' => 'DevGrandAdmiral',
                'roles' => ['grand_admiral'],
                'verified' => true,
            ],
            'director' => [
                'label' => 'Director',
                'description' => 'Verified director account with global access.',
                'name' => 'Dev Director',
                'discord_name' => 'Dev Director',
                'discord_id' => 'dev-director',
                'rsi_handle' => 'DevDirector',
                'roles' => ['director'],
                'verified' => true,
            ],
            'tech_director' => [
                'label' => 'Tech Director',
                'description' => 'Verified technical director account.',
                'name' => 'Dev Tech Director',
                'discord_name' => 'Dev Tech Director',
                'discord_id' => 'dev-tech-director',
                'rsi_handle' => 'DevTechDirector',
                'roles' => ['tech_director'],
                'verified' => true,
            ],
            'verify_preview' => [
                'label' => 'Verify Preview',
                'description' => 'Discord-linked but RSI-unverified user for the real /verify step-two UI.',
                'name' => 'Verify Preview',
                'discord_name' => 'Verify Preview',
                'discord_id' => 'dev-verify-preview',
                'rsi_handle' => 'VerifyPreview',
                'roles' => ['member'],
                'verified' => false,
            ],
        ];
    }

    public function persona(string $slug): array
    {
        return Arr::get($this->personas(), $slug)
            ?? abort(404);
    }

    public function ensurePersona(string $slug): User
    {
        $definition = $this->persona($slug);
        $roleSlugs = collect($definition['roles'] ?? [])
            ->filter()
            ->values();

        $roles = Role::query()
            ->whereIn('slug', $roleSlugs)
            ->get()
            ->keyBy('slug');

        foreach ($roleSlugs as $roleSlug) {
            abort_unless(
                $roles->has($roleSlug),
                500,
                "Missing role [{$roleSlug}] for dev auth persona [{$slug}]. Run the role seeder first."
            );
        }

        $primaryRole = (string) $roleSlugs->first();
        $email = $slug . '@' . config('dev_auth.persona_email_domain', 'dev.horizon.test');

        $user = User::query()
            ->where('discord_id', $definition['discord_id'])
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            $user = new User();
            $user->password = Hash::make(Str::random(40));
        }

        $user->fill([
            'name' => $definition['name'],
            'email' => $email,
            'discord_id' => $definition['discord_id'],
            'discord_name' => $definition['discord_name'],
            'discord_avatar' => null,
            'global_status' => User::STATUS_ACTIVE,
            'rsi_handle' => $definition['rsi_handle'],
            'rank' => $primaryRole,
            'rank_level' => RoleHierarchy::levelFor($primaryRole) ?: 1,
            'rsi_verified_at' => ($definition['verified'] ?? false) ? now() : null,
            'verification_code' => null,
            'verification_expires_at' => null,
        ]);
        $user->email_verified_at = now();
        $user->save();

        $user->roles()->sync($roles->pluck('id')->all());

        $this->syncSquadronMembership($user, $definition);

        return $user->fresh(['roles', 'squadrons']);
    }

    protected function syncSquadronMembership(User $user, array $definition): void
    {
        $squadronConfig = $definition['squadron'] ?? null;

        if (! is_array($squadronConfig)) {
            return;
        }

        $squadron = $this->resolveSquadron($squadronConfig);

        SquadronMember::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'squadron_id' => $squadron->id,
            ],
            [
                'membership_status' => SquadronMember::STATUS_ACTIVE,
                'role' => $squadronConfig['membership_role'] ?? SquadronMember::ROLE_MEMBER,
                'joined_at' => now(),
                'left_at' => null,
                'removed_at' => null,
            ]
        );
    }

    protected function resolveSquadron(array $config): Squadron
    {
        $slug = trim((string) ($config['slug'] ?? ''));
        $name = trim((string) ($config['name'] ?? ''));

        $query = Squadron::query();

        if ($slug !== '' && $name !== '') {
            $query->where(function ($inner) use ($slug, $name) {
                $inner->where('slug', $slug)
                    ->orWhereRaw('LOWER(name) = ?', [Str::lower($name)]);
            });
        } elseif ($slug !== '') {
            $query->where('slug', $slug);
        } elseif ($name !== '') {
            $query->whereRaw('LOWER(name) = ?', [Str::lower($name)]);
        }

        $squadron = $query->first();

        if ($squadron) {
            return $squadron;
        }

        return Squadron::query()->create([
            'name' => $name !== '' ? $name : 'Penetrators',
            'slug' => $slug !== '' ? $slug : Str::slug($name !== '' ? $name : 'Penetrators'),
            'status' => SquadronStatus::Active->value,
            'recruiting' => true,
        ]);
    }
}
