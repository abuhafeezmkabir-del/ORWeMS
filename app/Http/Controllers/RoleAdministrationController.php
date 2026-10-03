<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only RBAC administration (Phase 3B).
 *
 * Deliberately small. This exposes exactly what is needed to see who holds what
 * and to grant a role to an account — and nothing more:
 *
 *   - no user CRUD (account provisioning and status are Phase 3D / later);
 *   - no role creation or deletion (roles are reference data, seeded, and
 *     deactivated rather than destroyed);
 *   - no permission creation (the vocabulary is seeded and version-controlled).
 *
 * The reason for keeping it this size is that every screen added here is a place
 * a privilege escalation could be introduced. What exists is enough to administer
 * the system correctly and is covered by tests.
 *
 * Authorization is enforced on the ROUTE, in the middleware stack, not here and
 * not in the view. Hiding a menu item is a usability measure; it is never the
 * control. Each method below can therefore assume it is only reached by an
 * operator holding the relevant permission, and re-checking would be noise.
 */
class RoleAdministrationController extends Controller
{
    /**
     * The role catalogue and the permissions each role confers.
     *
     * Requires `roles.view` — reading who can do what is itself sensitive, since
     * it discloses which accounts are worth targeting.
     */
    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->with('permissions:id,code,name')
            ->orderBy('name')
            ->get();

        return view('administration.roles.index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Every permission ORWeMS defines, grouped by resource family.
     *
     * Shown so the catalogue is reviewable by a human administrator rather than
     * only by whoever reads the seeder.
     */
    public function permissions(): View
    {
        $permissions = Permission::query()
            ->orderBy('code')
            ->get()
            ->groupBy(fn (Permission $permission): string => str($permission->code)->before('.')->toString());

        return view('administration.permissions.index', [
            'groups' => $permissions,
            'catalogueSize' => count(PermissionCatalog::codes()),
        ]);
    }

    /**
     * Assign a role to an operator account.
     *
     * Requires `users.assign_roles`, which is held separately from `roles.manage`
     * so that authority over people and authority over privileges are not the
     * same power.
     */
    public function assignToUser(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Refuse to let an administrator grant a role to their own account.
        // Self-assignment is the shortest path from an operator account to a
        // privileged one, and no legitimate workflow needs it: a second
        // administrator grants the role. This is enforced here, on the server,
        // because a hidden form field is not a control.
        if ($user->is($request->user())) {
            return back()->withErrors([
                'user_id' => 'You cannot assign a role to your own account.',
            ]);
        }

        // A withdrawn role grants nothing, so assigning one would look like it
        // succeeded while conferring no privilege at all.
        if (! $role->isActive()) {
            return back()->withErrors([
                'role' => 'This role has been deactivated and cannot be assigned.',
            ]);
        }

        $user->assignRole($role);

        return back()->with('success', sprintf(
            'Role "%s" assigned to %s.',
            $role->name,
            $user->name
        ));
    }
}