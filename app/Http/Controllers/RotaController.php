<?php

namespace App\Http\Controllers;

use App\Services\RotaPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RotaController
{
    public function __construct(private RotaPlanner $planner) {}

    private function businessId(Request $request): int
    {
        abort_unless($request->user()?->active && $request->user()?->business_id, 403);
        return (int) $request->user()->business_id;
    }

    private function timezone(int $businessId): string
    {
        return (string) (DB::table('businesses')->where('id', $businessId)->value('timezone') ?: 'UTC');
    }

    private function parseLocal(string $value, int $businessId): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $this->timezone($businessId));
    }

    private function audit(Request $request, int $businessId, ?int $shiftId, string $action, ?array $before, ?array $after): void
    {
        DB::table('rota_events')->insert([
            'business_id' => $businessId, 'rota_shift_id' => $shiftId, 'actor_id' => $request->user()->id,
            'action' => $action,
            'before_data' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_data' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'created_at' => now('UTC'),
        ]);
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $timezone = $this->timezone($businessId);
        $week = $request->query('week', CarbonImmutable::now($timezone)->startOfWeek()->toDateString());
        abort_unless(is_string($week) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $week) && checkdate((int) substr($week, 5, 2), (int) substr($week, 8, 2), (int) substr($week, 0, 4)), 422);
        $start = CarbonImmutable::parse($week, $timezone)->startOfWeek();
        $end = $start->addDays(6)->endOfDay();

        $shifts = DB::table('rota_shifts as s')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 's.user_id')->where('u.business_id', '=', $businessId);
        })->leftJoin('branches as b', function ($join) use ($businessId) {
            $join->on('b.id', '=', 's.branch_id')->where('b.business_id', '=', $businessId);
        })->where('s.business_id', $businessId)->where('s.starts_at', '<', $end->utc()->toDateTimeString())
            ->where('s.ends_at', '>', $start->utc()->toDateTimeString())
            ->select('s.*', 'u.name as employee_name', 'u.employee_code', 'b.name as branch_name')
            ->orderBy('s.starts_at')->get();
        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)->orderBy('name')->get(['id', 'name', 'employee_code']);
        $branches = DB::table('branches')->where('business_id', $businessId)->where('active', true)->orderBy('name')->get(['id', 'name']);
        $holidays = DB::table('business_holidays')->where('business_id', $businessId)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])->orderBy('holiday_date')->get();
        $leave = DB::table('leave_requests as l')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'l.user_id')->where('u.business_id', '=', $businessId);
        })->where('l.business_id', $businessId)->whereIn('l.status', ['approved', 'pending'])
            ->whereDate('l.start_date', '<=', $end->toDateString())->whereDate('l.end_date', '>=', $start->toDateString())
            ->select('l.*', 'u.name as employee_name')->orderBy('l.start_date')->get();

        return view('rota.index', compact('week', 'start', 'end', 'timezone', 'shifts', 'employees', 'branches', 'holidays', 'leave'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $values = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('business_id', $businessId)->where('active', true)],
            'title' => 'required|string|max:120', 'starts_at' => 'required|date_format:Y-m-d\\TH:i',
            'ends_at' => 'required|date_format:Y-m-d\\TH:i|after:starts_at',
            'break_minutes' => 'required|integer|min:0|max:600', 'notes' => 'nullable|string|max:2000',
        ]);
        $start = $this->parseLocal($values['starts_at'], $businessId);
        $end = $this->parseLocal($values['ends_at'], $businessId);
        if ($end->diffInHours($start) > 18) throw ValidationException::withMessages(['ends_at' => 'A shift cannot exceed 18 hours.']);
        if ($values['branch_id'] && !DB::table('employee_branches')->where('business_id', $businessId)->where('user_id', $values['user_id'])->where('branch_id', $values['branch_id'])->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'The employee is not assigned to this branch.']);
        }
        $shiftId = DB::transaction(function () use ($request, $businessId, $values, $start, $end) {
            $employee = DB::table('users')->where('business_id', $businessId)->where('id', $values['user_id'])->where('role', 'employee')->where('active', true)->lockForUpdate()->first();
            abort_unless($employee, 404);
            $this->planner->assertNoConflict($businessId, (int) $values['user_id'], $start, $end);
            $id = DB::table('rota_shifts')->insertGetId([
                'business_id' => $businessId, 'user_id' => $values['user_id'], 'branch_id' => $values['branch_id'] ?? null,
                'title' => $values['title'], 'starts_at' => $start->utc()->toDateTimeString(), 'ends_at' => $end->utc()->toDateTimeString(),
                'break_minutes' => $values['break_minutes'], 'notes' => $values['notes'] ?? null, 'status' => 'draft',
                'created_by' => $request->user()->id, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $this->audit($request, $businessId, $id, 'shift_created', null, $values);
            return $id;
        });
        return back()->with('status', 'Shift saved as a draft. Review the week and publish it when ready.');
    }

    public function publish(Request $request, int $shiftId)
    {
        $businessId = $this->businessId($request);
        DB::transaction(function () use ($request, $businessId, $shiftId) {
            $shift = DB::table('rota_shifts')->where('business_id', $businessId)->where('id', $shiftId)->lockForUpdate()->first();
            abort_unless($shift, 404);
            if ($shift->status !== 'draft') throw ValidationException::withMessages(['shift' => 'Only draft shifts can be published.']);
            DB::table('users')->where('business_id', $businessId)->where('id', $shift->user_id)->lockForUpdate()->firstOrFail();
            $this->planner->assertNoConflict($businessId, (int) $shift->user_id,
                CarbonImmutable::parse($shift->starts_at, 'UTC')->setTimezone($this->timezone($businessId)),
                CarbonImmutable::parse($shift->ends_at, 'UTC')->setTimezone($this->timezone($businessId)), $shiftId);
            DB::table('rota_shifts')->where('business_id', $businessId)->where('id', $shiftId)->update([
                'status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $this->audit($request, $businessId, $shiftId, 'shift_published', ['status' => 'draft'], ['status' => 'published']);
        });
        return back()->with('status', 'Shift published. It is now visible to the employee in the app API.');
    }

    public function delete(Request $request, int $shiftId)
    {
        $businessId = $this->businessId($request);
        DB::transaction(function () use ($request, $businessId, $shiftId) {
            $shift = DB::table('rota_shifts')->where('business_id', $businessId)->where('id', $shiftId)->lockForUpdate()->first();
            abort_unless($shift, 404);
            if ($shift->status === 'cancelled') throw ValidationException::withMessages(['shift' => 'This shift is already cancelled.']);
            DB::table('rota_shifts')->where('business_id', $businessId)->where('id', $shiftId)->update(['status' => 'cancelled', 'updated_at' => now('UTC')]);
            $this->audit($request, $businessId, $shiftId, 'shift_cancelled', (array) $shift, ['status' => 'cancelled']);
        });
        return back()->with('status', 'Shift cancelled. The change is recorded in the rota history.');
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'employee' && $user->active, 403);
        $businessId = (int) $user->business_id;
        $timezone = $this->timezone($businessId);
        $from = CarbonImmutable::now($timezone)->startOfDay();
        $until = $from->addDays(60)->endOfDay();
        $shifts = DB::table('rota_shifts as s')->leftJoin('branches as b', function ($join) use ($businessId) {
            $join->on('b.id', '=', 's.branch_id')->where('b.business_id', '=', $businessId);
        })->where('s.business_id', $businessId)->where('s.user_id', $user->id)->where('s.status', 'published')
            ->where('s.starts_at', '<', $until->utc()->toDateTimeString())
            ->where('s.ends_at', '>', $from->utc()->toDateTimeString())
            ->select('s.id', 's.title', 's.starts_at', 's.ends_at', 's.break_minutes', 's.notes', 'b.name as branch_name')
            ->orderBy('s.starts_at')->get()->map(function ($shift) use ($timezone) {
                $shift->starts_at = CarbonImmutable::parse($shift->starts_at, 'UTC')->setTimezone($timezone)->toIso8601String();
                $shift->ends_at = CarbonImmutable::parse($shift->ends_at, 'UTC')->setTimezone($timezone)->toIso8601String();
                return $shift;
            });
        return response()->json(['shifts' => $shifts]);
    }
}
