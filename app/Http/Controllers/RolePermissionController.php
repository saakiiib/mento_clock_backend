<?php

namespace App\Http\Controllers;

use App\Services\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolePermissionController
{
    public function __construct(private WorkspacePermissions $permissions) {}

    private function businessId(Request $request): int
    {
        abort_unless($request->user()?->active && $request->user()->business_id, 403);
        $this->permissions->require($request->user(), 'roles.manage');
        return (int) $request->user()->business_id;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        WorkspacePermissions::seedDefaultRoles($businessId);
        $roles = DB::table('business_roles')->where('business_id', $businessId)->orderBy('name')->get()->map(function ($role) {
            if (! is_array($role->permissions)) {
                $role->permissions = json_decode($role->permissions, true) ?: [];
            }

            return $role;
        });
        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->orderBy('name')->get(['id', 'name', 'employee_code', 'business_role_id']);
        $permissionLabels = WorkspacePermissions::PERMISSIONS;
        return view('roles.index', compact('roles', 'employees', 'permissionLabels'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $values = $request->validate([
            'name' => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(array_keys(WorkspacePermissions::PERMISSIONS))],
        ]);
        $code = 'custom_'.substr(hash('sha256', strtolower(trim($values['name']).'|'.microtime(true))), 0, 16);
        $permissions = array_values(array_unique($values['permissions'] ?? []));
        if ($permissions) $permissions[] = 'workspace.access';
        $roleId = DB::table('business_roles')->insertGetId([
            'business_id' => $businessId, 'code' => $code, 'name' => $values['name'],
            'permissions' => json_encode(array_values(array_unique($permissions)), JSON_THROW_ON_ERROR),
            'is_system' => false, 'created_by' => $request->user()->id, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        DB::table('hr_module_events')->insert(['business_id' => $businessId, 'actor_id' => $request->user()->id, 'subject_type' => 'business_role', 'subject_id' => $roleId,
            'action' => 'role_created', 'details' => json_encode(['name' => $values['name'], 'permissions' => $permissions], JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        return back()->with('status', 'Role created. Assign it to employees who need client-portal access.');
    }

    public function update(Request $request, int $roleId)
    {
        $businessId = $this->businessId($request);
        $role = DB::table('business_roles')->where('business_id', $businessId)->where('id', $roleId)->firstOrFail();
        $values = $request->validate([
            'name' => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(array_keys(WorkspacePermissions::PERMISSIONS))],
        ]);
        $permissions = array_values(array_unique($values['permissions'] ?? []));
        if ($permissions) $permissions[] = 'workspace.access';
        DB::table('business_roles')->where('id', $role->id)->update([
            'name' => $values['name'], 'permissions' => json_encode(array_values(array_unique($permissions)), JSON_THROW_ON_ERROR), 'updated_at' => now('UTC'),
        ]);
        DB::table('hr_module_events')->insert(['business_id' => $businessId, 'actor_id' => $request->user()->id, 'subject_type' => 'business_role', 'subject_id' => $role->id,
            'action' => 'role_permissions_updated', 'details' => json_encode(['before_permissions' => is_array($role->permissions) ? $role->permissions : json_decode($role->permissions, true), 'after_permissions' => $permissions], JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        return back()->with('status', 'Role permissions updated.');
    }

    public function assign(Request $request)
    {
        $businessId = $this->businessId($request);
        $values = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('business_id', $businessId)->where('role', 'employee')],
            'business_role_id' => ['nullable', 'integer', Rule::exists('business_roles', 'id')->where('business_id', $businessId)],
        ]);
        $previousRole = DB::table('users')->where('business_id', $businessId)->where('id', $values['employee_id'])->value('business_role_id');
        DB::table('users')->where('business_id', $businessId)->where('id', $values['employee_id'])->update([
            'business_role_id' => $values['business_role_id'] ?? null, 'updated_at' => now('UTC'),
        ]);
        DB::table('hr_module_events')->insert(['business_id' => $businessId, 'actor_id' => $request->user()->id, 'subject_type' => 'user', 'subject_id' => $values['employee_id'],
            'action' => 'portal_role_assigned', 'details' => json_encode(['previous_role_id' => $previousRole, 'business_role_id' => $values['business_role_id'] ?? null], JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        return back()->with('status', 'Portal role assignment updated. The employee must sign in again to use any newly granted web access.');
    }
}
