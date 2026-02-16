<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class AdminUserController extends Controller
{
    use AuthorizesRequests;

    /**
     * UPDATE USER FIELDS
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
     * UPDATE USER ROLES
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
}
