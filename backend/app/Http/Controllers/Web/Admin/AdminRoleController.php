<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Handles admin-only role mutation actions from the dashboard.
 */
class AdminRoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new application role.
     */
    public function store(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')],
        ]);

        Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Role created.');
    }

    /**
     * Update an existing application role.
     */
    public function update(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id' => ['required', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'slug')->ignore($request->input('id')),
            ],
        ]);

        $role = Role::findOrFail($data['id']);
        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Role updated.');
    }

    /**
     * Delete a role after detaching related users and permissions.
     */
    public function destroy(Request $request)
    {
        $this->authorize('access-admin-panel');

        $data = $request->validate([
            'id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::findOrFail($data['id']);

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        return redirect()
            ->back()
            ->with('success', 'Role deleted.');
    }
}
