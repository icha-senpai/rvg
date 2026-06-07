<?php

namespace App\Services;

use App\Domain\AccessControl\AccessService;
use App\Domain\AccessControl\PermissionRegistry;
use App\Domain\AccessControl\RoleHierarchy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RolePreviewService
{
    public const SESSION_KEY = 'role_preview.role_slug';
    public const REQUEST_ORIGINAL_USER_KEY = 'role_preview.original_user';

    protected const ALLOWED_ROLE_SLUGS = [
        'member',
        'lieutenant',
        'cit',
        'commander',
        'wing_commander',
        'admiral',
        'grand_admiral',
        'director',
        'tech_director',
    ];

    public function __construct(
        protected AccessService $access,
    ) {}

    public function canManage(?User $user): bool
    {
        return $user instanceof User && $this->access->isDirectorLike($user);
    }

    public function managerUser(Request $request, ?User $fallback = null): ?User
    {
        $original = $request->attributes->get(self::REQUEST_ORIGINAL_USER_KEY);

        if ($original instanceof User) {
            return $original;
        }

        return $fallback;
    }

    public function activeRoleSlug(Request $request, ?User $user): ?string
    {
        if (! $this->canManage($user)) {
            return null;
        }

        $slug = $request->session()->get(self::SESSION_KEY);

        if (! is_string($slug) || ! in_array($slug, self::ALLOWED_ROLE_SLUGS, true)) {
            return null;
        }

        return $this->roleForSlug($slug)?->slug;
    }

    public function setActiveRoleSlug(Request $request, ?User $user, ?string $slug): void
    {
        if (! $this->canManage($user)) {
            abort(403);
        }

        $normalized = is_string($slug) ? trim($slug) : '';

        if ($normalized === '') {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        if (! in_array($normalized, self::ALLOWED_ROLE_SLUGS, true) || ! $this->roleForSlug($normalized)) {
            abort(422, 'Unknown preview role.');
        }

        $request->session()->put(self::SESSION_KEY, $normalized);
    }

    public function projectedUser(?User $user, ?string $roleSlug): ?User
    {
        if (! $user || ! $roleSlug) {
            return $user;
        }

        $role = $this->roleForSlug($roleSlug);

        if (! $role) {
            return $user;
        }

        $projected = clone $user;
        $projected->setRelation('roles', collect([$role]));
        $projected->setAttribute('rank', $role->slug);
        $projected->setAttribute('rank_level', RoleHierarchy::levelFor($role->slug) ?: 1);

        return $projected;
    }

    public function projectedPermissionMap(?User $user, ?string $roleSlug): array
    {
        if (! $user) {
            return [];
        }

        if (! $roleSlug) {
            return $this->realPermissionMap($user);
        }

        $role = $this->roleForSlug($roleSlug);
        $permissionSlugSet = array_fill_keys(
            $role?->permissions?->pluck('slug')->all() ?? [],
            true
        );

        $permissions = [];

        foreach (PermissionRegistry::all() as $slug) {
            $permissions[$slug] = isset($permissionSlugSet[$slug]);
        }

        return $permissions;
    }

    public function share(Request $request, ?User $user): array
    {
        $canManage = $this->canManage($user);
        $activeSlug = $this->activeRoleSlug($request, $user);

        return [
            'canManage' => $canManage,
            'activeRoleSlug' => $activeSlug,
            'activeRoleLabel' => $activeSlug ? $this->roleLabel($activeSlug) : null,
            'options' => $canManage ? $this->previewOptions() : [],
        ];
    }

    public function projectedLedgerVisibility(array $permissionMap): bool
    {
        if (! (bool) config('services.ledger.enabled', false)) {
            return false;
        }

        return (bool) ($permissionMap['ledger.view-own'] ?? false)
            || (bool) ($permissionMap['ledger.view-any'] ?? false)
            || (bool) ($permissionMap['ledger.manage-org-ledger'] ?? false)
            || (bool) ($permissionMap['ledger.manage-squadron-ledger'] ?? false);
    }

    protected function previewOptions(): array
    {
        return $this->previewRoles()
            ->map(fn (Role $role) => [
                'value' => $role->slug,
                'label' => $role->name,
            ])
            ->values()
            ->all();
    }

    protected function realPermissionMap(User $user): array
    {
        if ($this->access->isDirectorLike($user)) {
            return array_fill_keys(PermissionRegistry::all(), true);
        }

        $permissionSlugSet = array_fill_keys(
            $this->access->context($user)->permissions()->pluck('slug')->all(),
            true
        );

        $permissions = [];

        foreach (PermissionRegistry::all() as $slug) {
            $permissions[$slug] = isset($permissionSlugSet[$slug]);
        }

        return $permissions;
    }

    protected function previewRoles(): Collection
    {
        return Role::query()
            ->whereIn('slug', self::ALLOWED_ROLE_SLUGS)
            ->with('permissions:id,slug')
            ->get()
            ->sortBy(fn (Role $role) => RoleHierarchy::levelFor($role->slug))
            ->values();
    }

    protected function roleForSlug(string $slug): ?Role
    {
        return $this->previewRoles()->firstWhere('slug', $slug);
    }

    protected function roleLabel(string $slug): ?string
    {
        return $this->roleForSlug($slug)?->name;
    }
}
