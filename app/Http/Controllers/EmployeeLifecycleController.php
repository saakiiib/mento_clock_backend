<?php

namespace App\Http\Controllers;

use App\Services\EmployeeLifecycle;
use App\Services\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeLifecycleController
{
    public function __construct(private EmployeeLifecycle $lifecycle, private WorkspacePermissions $permissions) {}

    private function businessId(Request $request, string $permission): int
    {
        $user = $request->user();
        abort_unless($user?->active && $user->business_id, 403);
        $this->permissions->require($user, $permission);
        return (int) $user->business_id;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request, 'lifecycle.view');
        $cases = DB::table('employee_lifecycle_cases as c')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'c.user_id')->where('u.business_id', '=', $businessId);
        })->where('c.business_id', $businessId)->orderByDesc('c.created_at')
            ->select('c.*', 'u.name as employee_name', 'u.employee_code')->get();
        $tasks = DB::table('employee_lifecycle_tasks')->where('business_id', $businessId)
            ->orderByRaw("CASE status WHEN 'blocked' THEN 0 WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END")
            ->orderBy('due_date')->get()->groupBy('case_id');
        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)->orderBy('name')->get(['id', 'name', 'employee_code']);
        return view('lifecycle.index', compact('cases', 'tasks', 'employees'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request, 'lifecycle.manage');
        $values = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)],
            'type' => ['required', Rule::in(['onboarding', 'offboarding'])],
            'effective_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:3000',
        ]);
        $caseId = DB::transaction(function () use ($request, $businessId, $values) {
            $employee = DB::table('users')->where('business_id', $businessId)->where('id', $values['user_id'])->lockForUpdate()->first();
            abort_unless($employee && $employee->role === 'employee', 404);
            if ($values['type'] === 'offboarding' && !$employee->active) throw ValidationException::withMessages(['user_id' => 'Only an active employee can start an offboarding workflow.']);
            $existing = DB::table('employee_lifecycle_cases')->where('business_id', $businessId)->where('user_id', $employee->id)
                ->where('type', $values['type'])->whereIn('status', ['in_progress', 'scheduled'])->exists();
            if ($existing) throw ValidationException::withMessages(['user_id' => 'This employee already has an open '.$values['type'].' case.']);
            $id = $this->lifecycle->open($businessId, (int) $employee->id, $values['type'], $values['effective_date'] ?? null, (int) $request->user()->id);
            if (!empty($values['notes'])) DB::table('employee_lifecycle_cases')->where('id', $id)->update(['notes' => $values['notes']]);
            return $id;
        });
        return back()->with('status', ucfirst($values['type']).' checklist created.');
    }

    public function updateTask(Request $request, int $taskId)
    {
        $businessId = $this->businessId($request, 'lifecycle.manage');
        $values = $request->validate(['status' => ['required', Rule::in(['pending', 'in_progress', 'complete', 'blocked'])], 'notes' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($request, $businessId, $taskId, $values) {
            $task = DB::table('employee_lifecycle_tasks')->where('business_id', $businessId)->where('id', $taskId)->lockForUpdate()->first();
            abort_unless($task, 404);
            $case = DB::table('employee_lifecycle_cases')->where('business_id', $businessId)->where('id', $task->case_id)->lockForUpdate()->firstOrFail();
            abort_if($case->status !== 'in_progress', 409, 'This workflow is already closed.');
            DB::table('employee_lifecycle_tasks')->where('id', $taskId)->update([
                'status' => $values['status'], 'notes' => $values['notes'] ?? null,
                'completed_by' => $values['status'] === 'complete' ? $request->user()->id : null,
                'completed_at' => $values['status'] === 'complete' ? now('UTC') : null, 'updated_at' => now('UTC'),
            ]);
            DB::table('hr_module_events')->insert(['business_id' => $businessId, 'actor_id' => $request->user()->id, 'subject_type' => 'lifecycle_task', 'subject_id' => $taskId,
                'action' => 'task_status_changed', 'details' => json_encode(['before' => $task->status, 'after' => $values['status']], JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        });
        return back()->with('status', 'Checklist task updated.');
    }

    public function complete(Request $request, int $caseId)
    {
        $businessId = $this->businessId($request, 'lifecycle.manage');
        $scheduledForLater = false;
        DB::transaction(function () use ($request, $businessId, $caseId, &$scheduledForLater) {
            $case = DB::table('employee_lifecycle_cases')->where('business_id', $businessId)->where('id', $caseId)->lockForUpdate()->first();
            abort_unless($case, 404);
            abort_if($case->status !== 'in_progress', 409, 'This workflow is already closed.');
            if (DB::table('employee_lifecycle_tasks')->where('business_id', $businessId)->where('case_id', $caseId)->where('status', '!=', 'complete')->exists()) {
                throw ValidationException::withMessages(['case' => 'Complete every checklist task before closing this workflow.']);
            }
            $now = now('UTC');
            $timezone = DB::table('businesses')->where('id', $businessId)->value('timezone') ?: 'UTC';
            $scheduledForLater = $case->type === 'offboarding' && $case->effective_date && $case->effective_date > now($timezone)->toDateString();
            DB::table('employee_lifecycle_cases')->where('id', $caseId)->update([
                'status' => $scheduledForLater ? 'scheduled' : 'complete',
                'completed_at' => $scheduledForLater ? null : $now, 'updated_at' => $now,
            ]);
            if ($case->type === 'offboarding' && !$scheduledForLater) {
                DB::table('users')->where('business_id', $businessId)->where('id', $case->user_id)->update([
                    'active' => false, 'employment_end_date' => $case->effective_date ?: $now->toDateString(), 'updated_at' => $now,
                ]);
                DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->where('tokenable_id', $case->user_id)->delete();
                DB::table('sessions')->where('user_id', $case->user_id)->delete();
            }
            DB::table('hr_module_events')->insert([
                'business_id' => $businessId, 'actor_id' => $request->user()->id, 'subject_type' => 'lifecycle_case',
                'subject_id' => $caseId, 'action' => $scheduledForLater ? 'offboarding_scheduled' : $case->type.'_completed', 'details' => null, 'created_at' => $now,
            ]);
        });
        return back()->with('status', $scheduledForLater ? 'Checklist complete. Employee access will be revoked on the recorded last working date.' : 'Workflow completed. Offboarding access has been revoked where applicable.');
    }
}
