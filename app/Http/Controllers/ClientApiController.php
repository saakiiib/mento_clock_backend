<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceReport;
use App\Services\BusinessCapacity;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ClientApiController
{
    public function __construct(private AttendanceReport $reports, private BusinessCapacity $capacity) {}

    private function businessId(Request $request): int
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Business administrator access is required.');
        return (int) $request->user()->business_id;
    }

    private function employee(int $businessId, int $id): User
    {
        return User::query()->where('business_id', $businessId)->where('role', 'employee')->findOrFail($id);
    }

    private function branchRules(int $businessId): array
    {
        return ['integer', Rule::exists('branches', 'id')->where('business_id', $businessId)->where('active', true)];
    }

    public function dashboard(Request $request)
    {
        $businessId = $this->businessId($request);
        $business = DB::table('businesses')->find($businessId);
        $today = Carbon::now($business->timezone)->toDateString();
        $todayReport = $this->reports->make($businessId, ['period' => 'daily', 'date' => $today]);
        return response()->json([
            'business' => $business->name,
            'timezone' => $business->timezone,
            'employees' => User::where('business_id', $businessId)->where('role', 'employee')->count(),
            'active_employees' => User::where('business_id', $businessId)->where('role', 'employee')->where('active', true)->count(),
            'branches' => DB::table('branches')->where('business_id', $businessId)->where('active', true)->count(),
            'branch_limit' => (int) $this->capacity->profile($businessId)->branch_limit,
            'employees_per_branch_limit' => (int) $this->capacity->profile($businessId)->employees_per_branch_limit,
            'currently_clocked_in' => DB::table('attendance_records')->where('business_id', $businessId)->whereNull('clock_out')->count(),
            'today' => $todayReport,
        ]);
    }

    public function employees(Request $request)
    {
        $businessId = $this->businessId($request);
        $employees = User::where('business_id', $businessId)->where('role', 'employee')->orderBy('name')->get();
        $assignments = DB::table('employee_branches')->join('branches', function ($join) use ($businessId) {
            $join->on('branches.id', '=', 'employee_branches.branch_id')->where('branches.business_id', '=', $businessId);
        })->where('employee_branches.business_id', $businessId)->select('employee_branches.user_id', 'branches.id', 'branches.name')->get()->groupBy('user_id');
        return response()->json(['data' => $employees->map(fn($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'employee_code' => $u->employee_code, 'job_title' => $u->job_title,
            'employment_start_date' => $u->employment_start_date,
            'emergency_contact_name' => $u->emergency_contact_name,
            'emergency_contact_phone' => $u->emergency_contact_phone, 'active' => (bool) $u->active,
            'branches' => ($assignments[$u->id] ?? collect())->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values(),
        ])->values()]);
    }

    public function storeEmployee(Request $request)
    {
        $businessId = $this->businessId($request);
        $v = $request->validate([
            'name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email|unique:platform_admins,email',
            'password' => 'required|string|min:8|max:100', 'phone' => 'nullable|string|max:40',
            'employee_code' => 'nullable|string|max:40', 'branches' => 'required|array|min:1',
            'job_title' => 'nullable|string|max:100', 'employment_start_date' => 'nullable|date_format:Y-m-d',
            'emergency_contact_name' => 'nullable|string|max:100', 'emergency_contact_phone' => 'nullable|string|max:40',
            'branches.*' => $this->branchRules($businessId),
        ]);
        $employee = DB::transaction(function () use ($businessId, $v) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $this->capacity->ensureEmployeeAssignments($businessId, $v['branches']);
            $employee = User::create([
                'business_id' => $businessId, 'name' => $v['name'], 'email' => $v['email'],
                'password' => $v['password'], 'phone' => $v['phone'] ?? null,
                'employee_code' => $v['employee_code'] ?? null, 'job_title' => $v['job_title'] ?? null,
                'employment_start_date' => $v['employment_start_date'] ?? null,
                'emergency_contact_name' => $v['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $v['emergency_contact_phone'] ?? null,
                'role' => 'employee', 'active' => true,
            ]);
            foreach (array_unique($v['branches']) as $branchId) {
                DB::table('employee_branches')->insert(['business_id' => $businessId, 'user_id' => $employee->id, 'branch_id' => $branchId]);
            }
            return $employee;
        });
        return response()->json(['message' => 'Employee created.', 'id' => $employee->id], 201);
    }

    public function updateEmployee(Request $request, int $id)
    {
        $businessId = $this->businessId($request);
        $employee = $this->employee($businessId, $id);
        $v = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id), Rule::unique('platform_admins', 'email')],
            'password' => 'nullable|string|min:8|max:100', 'phone' => 'nullable|string|max:40',
            'employee_code' => 'nullable|string|max:40', 'branches' => 'required|array|min:1',
            'job_title' => 'nullable|string|max:100', 'employment_start_date' => 'nullable|date_format:Y-m-d',
            'emergency_contact_name' => 'nullable|string|max:100', 'emergency_contact_phone' => 'nullable|string|max:40',
            'branches.*' => $this->branchRules($businessId),
        ]);
        DB::transaction(function () use ($businessId, $employee, $v, $id) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $current = User::where('business_id', $businessId)->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($current->active) $this->capacity->ensureEmployeeAssignments($businessId, $v['branches'], $current->id);
            $current->update(collect($v)->except(['branches', 'password'])->all());
            if (!empty($v['password'])) $current->update(['password' => $v['password']]);
            $current->tokens()->delete();
            DB::table('employee_branches')->where('business_id', $businessId)->where('user_id', $current->id)->delete();
            foreach (array_unique($v['branches']) as $branchId) {
                DB::table('employee_branches')->insert(['business_id' => $businessId, 'user_id' => $current->id, 'branch_id' => $branchId]);
            }
        });
        return response()->json(['message' => 'Employee updated. They need to sign in again.']);
    }

    public function toggleEmployee(Request $request, int $id)
    {
        $businessId = $this->businessId($request);
        $employee = $this->employee($businessId, $id);
        $active = DB::transaction(function () use ($businessId, $id) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $employee = User::where('business_id', $businessId)->where('role', 'employee')->where('id', $id)->lockForUpdate()->firstOrFail();
            if (!$employee->active) $this->capacity->ensureCanActivateEmployee($businessId, $employee->id);
            $employee->active = !$employee->active;
            $employee->save();
            if (!$employee->active) $employee->tokens()->delete();
            return $employee->active;
        });
        return response()->json(['message' => $active ? 'Employee activated.' : 'Employee deactivated.', 'active' => $active]);
    }

    public function branches(Request $request)
    {
        $businessId = $this->businessId($request);
        $rows = DB::table('branches')->where('business_id', $businessId)->orderBy('name')->get();
        return response()->json(['data' => $rows->map(fn($b) => [
            'id' => $b->id, 'name' => $b->name, 'address' => $b->address,
            'latitude' => (float) $b->latitude, 'longitude' => (float) $b->longitude,
            'radius_m' => (int) $b->radius_m, 'active' => (bool) $b->active,
            'employee_count' => $this->capacity->activeEmployeeCount($businessId, (int) $b->id),
            'employee_limit' => $this->capacity->employeeLimit($businessId, (int) $b->id),
        ])->values()]);
    }

    public function storeBranch(Request $request)
    {
        $businessId = $this->businessId($request);
        $v = $request->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000']);
        $id = DB::transaction(function () use ($businessId, $v) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $this->capacity->ensureBranchSlot($businessId);
            return DB::table('branches')->insertGetId([...$v, 'business_id'=>$businessId, 'active'=>true, 'created_at'=>now('UTC'), 'updated_at'=>now('UTC')]);
        });
        return response()->json(['message'=>'Workplace created.', 'id'=>$id], 201);
    }

    public function updateBranch(Request $request, int $id)
    {
        $businessId = $this->businessId($request);
        $branch = DB::table('branches')->where('business_id', $businessId)->where('id', $id)->first();
        abort_unless($branch, 404);
        $v = $request->validate(['name'=>'required|string|max:100','address'=>'required|string|max:255','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','radius_m'=>'required|integer|min:50|max:1000','active'=>'sometimes|boolean']);
        DB::transaction(function () use ($businessId, $id, $branch, $v) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            if (array_key_exists('active', $v) && $v['active'] && !$branch->active) $this->capacity->ensureBranchSlot($businessId);
            DB::table('branches')->where('business_id', $businessId)->where('id', $id)->update([...$v, 'updated_at'=>now('UTC')]);
        });
        return response()->json(['message'=>'Workplace updated.']);
    }

    public function attendance(Request $request)
    {
        $businessId = $this->businessId($request);
        $business = DB::table('businesses')->find($businessId);
        $v = $request->validate([
            'date'=>'nullable|date_format:Y-m-d',
            'employee_id'=>['nullable','integer',Rule::exists('users','id')->where('business_id',$businessId)->where('role','employee')],
            'branch_id'=>['nullable','integer',Rule::exists('branches','id')->where('business_id',$businessId)],
        ]);
        $date = Carbon::parse($v['date'] ?? Carbon::now($business->timezone)->toDateString(), $business->timezone);
        $start = $date->copy()->startOfDay()->utc();
        $end = $date->copy()->addDay()->startOfDay()->utc();
        $query = DB::table('attendance_records as a')
            ->join('users as u','u.id','=','a.user_id')
            ->join('branches as b','b.id','=','a.branch_id')
            ->where('a.business_id',$businessId)->where('a.clock_in','<',$end)
            ->where(fn($q) => $q->whereNull('a.clock_out')->orWhere('a.clock_out','>',$start));
        if (!empty($v['employee_id'])) $query->where('a.user_id',$v['employee_id']);
        if (!empty($v['branch_id'])) $query->where('a.branch_id',$v['branch_id']);
        $rows = $query->orderByDesc('a.clock_in')->limit(300)->select('a.*','u.name as employee','u.employee_code','b.name as branch')->get();
        return response()->json(['data'=>$rows->map(fn($a)=>[
            'id'=>$a->id,'employee'=>$a->employee,'employee_code'=>$a->employee_code,'branch'=>$a->branch,
            'clock_in'=>Carbon::parse($a->clock_in,'UTC')->tz($business->timezone)->toIso8601String(),
            'clock_out'=>$a->clock_out ? Carbon::parse($a->clock_out,'UTC')->tz($business->timezone)->toIso8601String() : null,
            'status'=>$a->clock_out ? 'Completed' : 'Active',
        ])]);
    }

    public function correctAttendance(Request $request, int $id)
    {
        $businessId = $this->businessId($request);
        $v = $request->validate(['clock_in'=>'required|date','clock_out'=>'nullable|date|after:clock_in','reason'=>'required|string|min:5|max:500']);
        $business = DB::table('businesses')->find($businessId);
        DB::transaction(function () use ($request, $id, $businessId, $v, $business) {
            $candidate = DB::table('attendance_records')->where('business_id',$businessId)->where('id',$id)->first();
            abort_unless($candidate, 404);
            User::where('business_id',$businessId)->where('id',$candidate->user_id)->lockForUpdate()->firstOrFail();
            $record = DB::table('attendance_records')->where('business_id',$businessId)->where('id',$id)->lockForUpdate()->first();
            $in = Carbon::parse($v['clock_in'], $business->timezone)->utc();
            $out = empty($v['clock_out']) ? null : Carbon::parse($v['clock_out'], $business->timezone)->utc();
            abort_if($in->isFuture() || ($out && $out->isFuture()), 422, 'Attendance cannot be in the future.');
            abort_if(!$out && DB::table('attendance_records')->where('user_id',$record->user_id)->where('id','!=',$id)->whereNull('clock_out')->exists(), 409, 'Employee already has another open record.');
            $overlap = DB::table('attendance_records')->where('user_id',$record->user_id)->where('id','!=',$id)->where(fn($q)=>$q->whereNull('clock_out')->orWhere('clock_out','>',$in));
            if ($out) $overlap->where('clock_in','<',$out);
            abort_if($overlap->exists(), 422, 'This time overlaps another attendance record.');
            $after = ['clock_in'=>$in->toDateTimeString(), 'clock_out'=>$out?->toDateTimeString()];
            DB::table('attendance_records')->where('business_id',$businessId)->where('id',$id)->update([...$after,'updated_at'=>now()]);
            DB::table('audit_logs')->insert([
                'business_id'=>$businessId,'actor_id'=>$request->user()->id,'attendance_id'=>$id,
                'action'=>'corrected','reason'=>$v['reason'],
                'before'=>json_encode(['clock_in'=>$record->clock_in,'clock_out'=>$record->clock_out]),
                'after'=>json_encode($after),'created_at'=>now(),
            ]);
        });
        return response()->json(['message'=>'Attendance corrected and recorded in the audit log.']);
    }

    private function reportFilters(Request $request, int $businessId): array
    {
        return $request->validate([
            'period'=>'nullable|in:daily,weekly,monthly',
            'date'=>'nullable|date_format:Y-m-d',
            'employee_id'=>['nullable','integer',Rule::exists('users','id')->where('business_id',$businessId)->where('role','employee')],
            'branch_id'=>['nullable','integer',Rule::exists('branches','id')->where('business_id',$businessId)],
        ]);
    }

    public function report(Request $request)
    {
        $businessId = $this->businessId($request);
        return response()->json($this->reports->make($businessId, $this->reportFilters($request, $businessId)));
    }

    public function downloadReport(Request $request)
    {
        $businessId = $this->businessId($request);
        $filters = $this->reportFilters($request, $businessId);
        $format = $request->validate(['format'=>'required|in:pdf,csv'])['format'];
        $report = $this->reports->make($businessId, $filters);
        $filename = 'mentoclock-'.$report['start'].'.'.$format;
        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', ['report'=>$report])->setPaper('a4','landscape')->download($filename);
        }
        return response($this->reports->csv($report), 200, ['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$filename.'"']);
    }

    public function emailReport(Request $request)
    {
        $businessId = $this->businessId($request);
        $filters = $this->reportFilters($request, $businessId);
        $report = $this->reports->make($businessId, $filters);
        $email = $request->user()->email;
        $pdf = Pdf::loadView('reports.pdf', ['report'=>$report])->setPaper('a4','landscape')->output();
        $csv = $this->reports->csv($report);
        Mail::send('reports.email', ['report'=>$report], function ($mail) use ($email, $pdf, $csv, $report) {
            $mail->to($email)->subject('MentoClock attendance report · '.$report['start'].' to '.$report['end'])
                ->attachData($pdf,'attendance.pdf',['mime'=>'application/pdf'])
                ->attachData($csv,'attendance.csv',['mime'=>'text/csv']);
        });
        return response()->json(['message'=>'The report was emailed to your administrator account.']);
    }
}
