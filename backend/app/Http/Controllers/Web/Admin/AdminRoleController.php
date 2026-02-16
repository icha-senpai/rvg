<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdminRoleController extends Controller
{
    use AuthorizesRequests;

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
