<?php

namespace App\Http\Controllers;

use App\Services\LeaveManagement;
use App\Services\RotaPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveController
{
    public function __construct(private LeaveManagement $leave, private RotaPlanner $rota) {}

    private function businessId(Request $request): int
    {
        abort_unless($request->user()?->active && $request->user()?->business_id, 403);
        return (int) $request->user()->business_id;
    }

    private function timezone(int $businessId): string
    {
        return (string) (DB::table('businesses')->where('id', $businessId)->value('timezone') ?: 'UTC');
    }

    private function date(string $date, int $businessId): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->timezone($businessId));
    }

    private function adminEvent(Request $request, int $businessId, string $subject, ?int $subjectId, string $action, ?array $before, ?array $after): void
    {
        DB::table('leave_admin_events')->insert([
            'business_id' => $businessId, 'actor_id' => $request->user()->id,
            'subject_type' => $subject, 'subject_id' => $subjectId, 'action' => $action,
            'before_data' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_data' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'created_at' => now('UTC'),
        ]);
    }

    public function index(Request $request, string $tab = 'leave')
    {
        $businessId = $this->businessId($request);
        $policy = $this->leave->policy($businessId);
        $employees = DB::table('users as u')->leftJoin('employee_leave_profiles as p', function ($join) use ($businessId) {
            $join->on('p.user_id', '=', 'u.id')->where('p.business_id', '=', $businessId);
        })->where('u.business_id', $businessId)->where('u.role', 'employee')->where('u.active', true)
            ->select('u.id', 'u.name', 'u.employee_code', 'p.annual_entitlement_override', 'p.working_days')->orderBy('u.name')->get();

        $balances = [];
        foreach ($employees as $employee) $balances[$employee->id] = $this->leave->balance($businessId, (int) $employee->id);

        $requests = DB::table('leave_requests as r')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'r.user_id')->where('u.business_id', '=', $businessId);
        })->leftJoin('users as reviewer', function ($join) use ($businessId) {
            $join->on('reviewer.id', '=', 'r.reviewed_by')->where('reviewer.business_id', '=', $businessId);
        })->where('r.business_id', $businessId)
            ->select('r.*', 'u.name as employee_name', 'u.employee_code', 'reviewer.name as reviewer_name')
            ->orderByRaw("CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('r.start_date')->limit(250)->get();

        $today = CarbonImmutable::now($this->timezone($businessId))->startOfDay();
        $holidays = DB::table('business_holidays')->where('business_id', $businessId)
            ->whereBetween('holiday_date', [$today->toDateString(), $today->addMonths(12)->toDateString()])
            ->orderBy('holiday_date')->get();
        $adminEvents = DB::table('leave_admin_events as e')->leftJoin('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'e.actor_id')->where('u.business_id', '=', $businessId);
        })->where('e.business_id', $businessId)->select('e.*', 'u.name as actor_name')->orderByDesc('e.id')->limit(40)->get();

        return view('leave.index', compact('policy', 'employees', 'balances', 'requests', 'holidays', 'adminEvents', 'tab'));
    }

    public function calendar(Request $request)
    {
        return $this->index($request, 'calendar');
    }

    public function savePolicy(Request $request)
    {
        $businessId = $this->businessId($request);
        $values = $request->validate([
            'leave_year_start_month' => 'required|integer|min:1|max:12',
            'leave_year_start_day' => 'required|integer|min:1|max:31',
            'default_annual_entitlement' => 'required|integer|min:0|max:366',
            'holiday_region' => ['required', Rule::in(['england_wales', 'scotland', 'northern_ireland', 'custom'])],
        ]);
        if (!checkdate((int) $values['leave_year_start_month'], (int) $values['leave_year_start_day'], 2025)) {
            throw ValidationException::withMessages(['leave_year_start_day' => 'Choose a valid month and day for the leave year.']);
        }

        $before = DB::table('business_leave_policies')->where('business_id', $businessId)->first();
        DB::transaction(function () use ($request, $businessId, $values, $before) {
            $policyExists = DB::table('business_leave_policies')->where('business_id', $businessId)->exists();
            $policyData = [...$values, 'updated_at' => now('UTC')];
            if (!$policyExists) $policyData['created_at'] = now('UTC');
            DB::table('business_leave_policies')->updateOrInsert(['business_id' => $businessId], $policyData);
            $this->adminEvent($request, $businessId, 'leave_policy', null, 'policy_updated', $before ? (array) $before : null, $values);
        });

        return back()->with('status', 'Leave policy saved. Annual entitlements are settings for your business; please confirm they match each employee’s contract.');
    }

    public function saveEmployeeProfile(Request $request, int $employeeId)
    {
        $businessId = $this->businessId($request);
        $employee = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('id', $employeeId)->first();
        abort_unless($employee, 404);
        $values = $request->validate([
            'annual_entitlement_override' => 'nullable|integer|min:0|max:366',
            'working_days' => 'required|array|min:1',
            'working_days.*' => ['required', Rule::in(LeaveManagement::WEEKDAYS)],
        ]);
        $days = array_values(array_unique($values['working_days']));
        $key = ['business_id' => $businessId, 'user_id' => $employeeId];
        $before = DB::table('employee_leave_profiles')->where($key)->first();
        $profileData = ['annual_entitlement_override' => $values['annual_entitlement_override'] ?? null,
            'working_days' => json_encode($days), 'updated_at' => now('UTC')];
        DB::transaction(function () use ($request, $businessId, $employeeId, $key, $profileData, $before, $values, $days) {
            if (!DB::table('employee_leave_profiles')->where($key)->exists()) $profileData['created_at'] = now('UTC');
            DB::table('employee_leave_profiles')->updateOrInsert($key, $profileData);
            $this->adminEvent($request, $businessId, 'employee_leave_profile', $employeeId, 'employee_leave_profile_updated',
                $before ? (array) $before : null, ['annual_entitlement_override' => $values['annual_entitlement_override'] ?? null, 'working_days' => $days]);
        });

        return back()->with('status', 'Employee leave allowance and working days saved.');
    }

    public function storeHoliday(Request $request)
    {
        $businessId = $this->businessId($request);
        $values = $request->validate([
            'name' => 'required|string|max:140',
            'region' => ['required', Rule::in(LeaveManagement::REGIONS)], 'notes' => 'nullable|string|max:1000',
            'is_closure' => 'nullable|boolean',
            'holiday_date' => ['required', 'date_format:Y-m-d', Rule::unique('business_holidays', 'holiday_date')->where('business_id', $businessId)->where('region', $request->input('region'))],
        ]);
        $values['is_closure'] = (bool) ($values['is_closure'] ?? false);
        DB::transaction(function () use ($request, $businessId, $values) {
            $holidayId = DB::table('business_holidays')->insertGetId([
                'business_id' => $businessId, ...$values, 'created_by' => $request->user()->id,
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $this->adminEvent($request, $businessId, 'holiday', $holidayId, 'holiday_created', null, $values);
        });
        return back()->with('status', 'Holiday added to your business calendar.');
    }

    public function deleteHoliday(Request $request, int $holidayId)
    {
        $businessId = $this->businessId($request);
        DB::transaction(function () use ($request, $businessId, $holidayId) {
            $holiday = DB::table('business_holidays')->where('business_id', $businessId)->where('id', $holidayId)->lockForUpdate()->first();
            abort_unless($holiday, 404);
            DB::table('business_holidays')->where('business_id', $businessId)->where('id', $holidayId)->delete();
            $this->adminEvent($request, $businessId, 'holiday', $holidayId, 'holiday_removed', (array) $holiday, null);
        });
        return back()->with('status', 'Holiday removed from your business calendar.');
    }

    public function review(Request $request, int $requestId)
    {
        $businessId = $this->businessId($request);
        $values = $request->validate(['decision' => 'required|in:approved,rejected', 'review_note' => 'nullable|string|max:2000']);

        DB::transaction(function () use ($businessId, $request, $requestId, $values) {
            $leaveRequest = DB::table('leave_requests')->where('business_id', $businessId)->where('id', $requestId)->lockForUpdate()->first();
            abort_unless($leaveRequest, 404);
            if ($leaveRequest->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Only pending requests can be reviewed.']);
            }
            $employee = DB::table('users')->where('business_id', $businessId)->where('id', $leaveRequest->user_id)->lockForUpdate()->first();
            abort_unless($employee && $employee->role === 'employee', 404);

            if ($values['decision'] === 'approved') {
                $start = $this->date($leaveRequest->start_date, $businessId);
                $end = $this->date($leaveRequest->end_date, $businessId);
                $this->leave->assertNoOverlap($businessId, (int) $employee->id, $start, $end, $requestId);
                $this->rota->assertLeaveCanBeApproved($businessId, (int) $employee->id, $start, $end);
                if ($leaveRequest->leave_type === 'annual') {
                    $period = $this->leave->yearFor($businessId, $start);
                    $approved = (float) DB::table('leave_requests')->where('business_id', $businessId)->where('user_id', $employee->id)
                        ->where('leave_type', 'annual')->where('status', 'approved')->whereBetween('start_date', [$period['start']->toDateString(), $period['end']->toDateString()])->sum('working_days');
                    $entitlement = $this->leave->entitlement($businessId, (int) $employee->id);
                    if ($approved + (float) $leaveRequest->working_days > $entitlement) {
                        throw ValidationException::withMessages(['decision' => 'Approving this request would exceed the employee’s configured annual entitlement.']);
                    }
                }
            }

            $from = $leaveRequest->status;
            DB::table('leave_requests')->where('business_id', $businessId)->where('id', $requestId)->update([
                'status' => $values['decision'], 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now('UTC'), 'review_note' => $values['review_note'] ?? null, 'updated_at' => now('UTC'),
            ]);
            DB::table('leave_request_events')->insert([
                'business_id' => $businessId, 'leave_request_id' => $requestId, 'actor_id' => $request->user()->id,
                'action' => $values['decision'], 'from_status' => $from, 'to_status' => $values['decision'],
                'note' => $values['review_note'] ?? null, 'created_at' => now('UTC'),
            ]);
        });

        return back()->with('status', 'Leave request '.$values['decision'].'.');
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'employee', 403);
        $businessId = (int) $user->business_id;
        $balance = $this->leave->balance($businessId, (int) $user->id, CarbonImmutable::now($this->timezone($businessId)));
        $policy = $this->leave->policy($businessId);
        $holidays = DB::table('business_holidays')->where('business_id', $businessId)
            ->whereBetween('holiday_date', [$balance['year_start'], $balance['year_end']])
            ->whereIn('region', ['all', 'local', $policy->holiday_region])->orderBy('holiday_date')->get(['name', 'holiday_date', 'region']);
        $requests = DB::table('leave_requests')->where('business_id', $businessId)->where('user_id', $user->id)
            ->orderByDesc('start_date')->limit(100)->get();
        return response()->json(['balance' => $balance, 'requests' => $requests, 'holidays' => $holidays]);
    }

    public function submit(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'employee' && $user->active, 403);
        $businessId = (int) $user->business_id;
        $values = $request->validate([
            'leave_type' => ['required', Rule::in(LeaveManagement::TYPES)],
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
        ]);
        $start = $this->date($values['start_date'], $businessId);
        $end = $this->date($values['end_date'], $businessId);
        if ($start->diffInDays($end) > 365) throw ValidationException::withMessages(['end_date' => 'A request cannot be longer than one year.']);
        $this->leave->assertWithinLeaveYear($businessId, $start, $end);
        $count = $this->leave->countWorkingDays($businessId, (int) $user->id, $start, $end);
        if ($count['days'] <= 0) throw ValidationException::withMessages(['start_date' => 'The selected dates contain no working days for this employee.']);

        DB::transaction(function () use ($businessId, $user, $values, $start, $end, $count) {
            DB::table('users')->where('business_id', $businessId)->where('id', $user->id)->lockForUpdate()->firstOrFail();
            $this->leave->assertNoOverlap($businessId, (int) $user->id, $start, $end);
            if ($values['leave_type'] === 'annual') {
                $balance = $this->leave->balance($businessId, (int) $user->id, $start);
                if ($balance['entitlement'] <= 0) throw ValidationException::withMessages(['leave_type' => 'Your annual leave entitlement has not been configured. Please contact your administrator.']);
                if ($count['days'] > $balance['remaining']) throw ValidationException::withMessages(['end_date' => 'This request is longer than your remaining annual leave balance.']);
            }
            $id = DB::table('leave_requests')->insertGetId([
                'business_id' => $businessId, 'user_id' => $user->id, 'leave_type' => $values['leave_type'],
                'start_date' => $values['start_date'], 'end_date' => $values['end_date'],
                'working_days' => $count['days'], 'reason' => $values['reason'] ?? null, 'status' => 'pending',
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            DB::table('leave_request_events')->insert([
                'business_id' => $businessId, 'leave_request_id' => $id, 'actor_id' => $user->id,
                'action' => 'submitted', 'from_status' => null, 'to_status' => 'pending', 'created_at' => now('UTC'),
            ]);
        });

        return response()->json(['message' => 'Leave request submitted for review.'], 201);
    }

    public function cancelMine(Request $request, int $requestId)
    {
        $user = $request->user();
        abort_unless($user?->role === 'employee', 403);
        $businessId = (int) $user->business_id;
        DB::transaction(function () use ($request, $user, $businessId, $requestId) {
            $leaveRequest = DB::table('leave_requests')->where('business_id', $businessId)->where('user_id', $user->id)->where('id', $requestId)->lockForUpdate()->first();
            abort_unless($leaveRequest, 404);
            if (!in_array($leaveRequest->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages(['request' => 'This request can no longer be cancelled.']);
            }
            if ($leaveRequest->status === 'approved' && $leaveRequest->start_date <= CarbonImmutable::now($this->timezone($businessId))->toDateString()) {
                throw ValidationException::withMessages(['request' => 'Approved leave that has started cannot be cancelled in the app. Please contact your administrator.']);
            }
            DB::table('leave_requests')->where('business_id', $businessId)->where('id', $requestId)->update(['status' => 'cancelled', 'updated_at' => now('UTC')]);
            DB::table('leave_request_events')->insert([
                'business_id' => $businessId, 'leave_request_id' => $requestId, 'actor_id' => $user->id,
                'action' => 'cancelled', 'from_status' => $leaveRequest->status, 'to_status' => 'cancelled', 'created_at' => now('UTC'),
            ]);
        });
        return response()->json(['message' => 'Leave request cancelled.']);
    }
}
