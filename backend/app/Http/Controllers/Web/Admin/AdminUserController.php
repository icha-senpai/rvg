<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Handles admin-only user mutation actions from the dashboard.
 */
class AdminUserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Update the editable profile and status fields for one user.
     */
    public function update(Request $request)
    {
        $this->authorize('access-admin-panel');

        $rsiHandle = trim((string) $request->input('rsi_handle', ''));
        $request->merge([
            'rsi_handle' => $rsiHandle !== '' ? $rsiHandle : null,
        ]);

        $data = $request->validate([
            'id'                  => ['required', 'exists:users,id'],
            'rsi_handle'           => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9-_]+$/',
                Rule::unique('users', 'rsi_handle')->ignore($request->input('id')),
            ],
            'rank'                => ['nullable', 'string', 'max:255'],
            'rank_level'          => ['nullable', 'integer', 'min:1'],
            'global_status'       => ['nullable', 'string', 'max:255'],
            'rsi_verified_at'      => ['nullable', 'date'],
            'bio'                 => ['nullable', 'string'],
            'region'              => ['nullable', 'string', 'in:EU,US,APAC'],
            'timezone'            => ['nullable', 'string', 'max:255'],
            'availability_status' => ['nullable', 'string', 'max:255'],
            'loa_note'            => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($data['id']);
        $user->fill($data)->save();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'User updated.');
    }

    /**
     * Replace the role assignments for one user.
     *
     * Role and permission caches are cleared immediately so the updated access
     * state takes effect on the next request.
     */
    public function updateRoles(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id'         => ['required', 'exists:users,id'],
            'role_ids'   => ['array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $user = User::findOrFail($data['id']);
        $user->roles()->sync($data['role_ids'] ?? []);

        Cache::forget("user_roles_{$user->id}");
        Cache::forget("user_permissions_{$user->id}");

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Roles updated successfully.');
    }

    public function unverify(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($data['id']);
        // Some environments contain duplicate local rows for one Discord account.
        // Clearing only the clicked row can leave another verified row available
        // for the next Discord login, so unverify the whole Discord-linked set.
        $usersToUnverify = User::query()
            ->when(
                filled($user->discord_id),
                fn ($query) => $query->where('discord_id', $user->discord_id),
                fn ($query) => $query->whereKey($user->id),
            )
            ->get();

        foreach ($usersToUnverify as $userToUnverify) {
            $userToUnverify->rsi_verified_at = null;
            $userToUnverify->global_status = 'pending';
            $userToUnverify->verification_code = null;
            $userToUnverify->verification_expires_at = null;
            $userToUnverify->remember_token = null;
            $userToUnverify->save();

            $userToUnverify->tokens()->delete();
            DB::table('sessions')->where('user_id', $userToUnverify->id)->delete();

            Cache::forget("user_roles_{$userToUnverify->id}");
            Cache::forget("user_permissions_{$userToUnverify->id}");
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'User marked as unverified.');
    }

    public function clearRememberedSessions(Request $request)
    {
        $this->authorize('access-admin-panel');

        User::query()->whereNotNull('remember_token')->update([
            'remember_token' => null,
        ]);

        DB::table('sessions')->delete();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'All remembered logins and web sessions were cleared.');
    }
}
