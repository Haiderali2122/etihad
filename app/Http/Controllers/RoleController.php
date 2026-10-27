<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()->withCount('users')->orderByDesc('is_super')->orderBy('name')->get(),
            'permissionCount' => count(Role::allPermissions()),
        ]);
    }

    public function create(): View
    {
        return view('roles.form', ['role' => new Role(['permissions' => []])]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'permissions' => array_values($request->validated('permissions', [])),
        ]);

        return redirect()->route('roles.index')->with('status', __('Role :name saved.', ['name' => $role->name]));
    }

    public function edit(Role $role): View
    {
        return view('roles.form', ['role' => $role]);
    }

    /**
     * A full-access role keeps all permissions; only its name and description change.
     */
    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'permissions' => $role->is_super ? [] : array_values($request->validated('permissions', [])),
        ]);

        return redirect()->route('roles.index')->with('status', __('Role :name saved.', ['name' => $role->name]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $blocker = match (true) {
            $role->is_super => __('The full-access role cannot be deleted.'),
            $role->users()->exists() => __('Move the :count users off :name before deleting it.', ['count' => $role->users()->count(), 'name' => $role->name]),
            default => null,
        };

        if ($blocker) {
            return back()->withErrors(['role' => $blocker]);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('status', __('Role :name deleted.', ['name' => $role->name]));
    }
}
