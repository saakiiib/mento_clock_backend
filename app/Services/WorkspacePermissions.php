<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkspacePermissions
{
    public static function seedDefaultRoles(int $businessId): void
    {
        $sets = [
            'hr_manager' => ['workspace.access', 'people.view', 'people.manage', 'attendance.view', 'attendance.correct', 'leave.view', 'leave.manage', 'rota.view', 'rota.manage', 'compliance.view', 'compliance.documents.download', 'compliance.manage', 'notices.view', 'notices.manage', 'reports.view', 'reports.export', 'organisation.view', 'organisation.manage', 'lifecycle.view', 'lifecycle.manage', 'reminders.view', 'roles.manage'],
            'rota_manager' => ['workspace.access', 'people.view', 'rota.view', 'rota.manage', 'leave.view', 'reports.view'],
            'supervisor' => ['workspace.access', 'people.view', 'attendance.view', 'leave.view', 'rota.view', 'notices.view', 'reports.view'],
            'report_viewer' => ['workspace.access', 'people.view', 'attendance.view', 'leave.view', 'rota.view', 'compliance.view', 'notices.view', 'reports.view', 'organisation.view', 'lifecycle.view', 'reminders.view'],
        ];
        foreach ($sets as $code => $permissions) {
            DB::table('business_roles')->insertOrIgnore([
                'business_id' => $businessId, 'code' => $code,
                'name' => ucwords(str_replace('_', ' ', $code)),
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'is_system' => true, 'updated_at' => now('UTC'), 'created_at' => now('UTC'),
            ]);
        }
    }

    public const PERMISSIONS = [
        'people.view' => 'View the employee directory', 'people.sensitive.view' => 'View full employee records and sensitive details', 'people.manage' => 'Create and edit employees',
        'attendance.view' => 'View attendance', 'attendance.correct' => 'Correct attendance records',
        'leave.view' => 'View leave and holidays', 'leave.manage' => 'Manage leave and holidays',
        'rota.view' => 'View rota', 'rota.manage' => 'Manage rota',
        'compliance.view' => 'View compliance and training records', 'compliance.documents.download' => 'Download private employee compliance documents', 'compliance.manage' => 'Manage compliance and training',
        'notices.view' => 'View company notices', 'notices.manage' => 'Manage company notices',
        'reports.view' => 'View HR and attendance reports', 'reports.export' => 'Export reports',
        'organisation.view' => 'View organisation profile', 'organisation.manage' => 'Edit organisation profile',
        'lifecycle.view' => 'View onboarding and offboarding', 'lifecycle.manage' => 'Manage onboarding and offboarding',
        'reminders.view' => 'View HR reminders', 'roles.manage' => 'Manage roles and permissions',
        'workspace.access' => 'Sign in to the client management portal',
    ];

    public function has(?User $user, string $permission): bool
    {
        if (!$user || !$user->active) return false;
        if ($user->role === 'admin') return true;
        if (!$user->business_role_id) return false;
        $role = DB::table('business_roles')->where('business_id', $user->business_id)->where('id', $user->business_role_id)->first();
        if (!$role) return false;
        $permissions = is_array($role->permissions) ? $role->permissions : json_decode($role->permissions ?? '[]', true);
        if (!is_array($permissions)) return false;
        $implied = [
            'people.manage' => ['people.view', 'people.sensitive.view'], 'people.sensitive.view' => ['people.view'],
            'attendance.correct' => ['attendance.view'], 'leave.manage' => ['leave.view'], 'rota.manage' => ['rota.view'],
            'compliance.manage' => ['compliance.view', 'compliance.documents.download'], 'notices.manage' => ['notices.view'], 'reports.export' => ['reports.view'],
            'organisation.manage' => ['organisation.view'], 'lifecycle.manage' => ['lifecycle.view'], 'roles.manage' => ['workspace.access'],
        ];
        return in_array($permission, $permissions, true) || collect($permissions)->contains(fn ($granted) => in_array($permission, $implied[$granted] ?? [], true));
    }

    public function require(?User $user, string $permission): void
    {
        abort_unless($this->has($user, $permission), 403, 'You do not have permission to perform this action.');
    }
}
