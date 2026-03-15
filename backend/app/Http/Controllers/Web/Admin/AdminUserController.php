<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

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

        $user->rsi_verified_at = null;
        $user->global_status = 'pending';
        $user->verification_code = null;
        $user->verification_expires_at = null;
        $user->save();

        $user->tokens()->delete();

        Cache::forget("user_roles_{$user->id}");
        Cache::forget("user_permissions_{$user->id}");

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'User marked as unverified.');
    }
}
