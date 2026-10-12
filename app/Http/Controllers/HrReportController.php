<?php

namespace App\Http\Controllers;

use App\Services\WorkspacePermissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrReportController
{
    public function __construct(private WorkspacePermissions $permissions) {}

    private function reportData(Request $request): array
    {
        $user = $request->user();
        abort_unless($user?->active && $user->business_id, 403);
        $this->permissions->require($user, 'reports.view');
        $businessId = (int) $user->business_id;
        $timezone = DB::table('businesses')->where('id', $businessId)->value('timezone') ?: 'UTC';
        $filters = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d']);
        $from = CarbonImmutable::parse($filters['from'] ?? CarbonImmutable::now($timezone)->startOfMonth()->toDateString(), $timezone)->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? CarbonImmutable::now($timezone)->toDateString(), $timezone)->endOfDay();
        abort_if($to->lt($from), 422, 'The end date must be on or after the start date.');
        abort_if($from->diffInDays($to) > 366, 422, 'Choose a reporting period no longer than one year.');
        $fromDate = $from->toDateString(); $toDate = $to->toDateString();
        $fromUtc = $from->utc()->toDateTimeString(); $toUtc = $to->utc()->toDateTimeString();

        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee');
        $activeCount = (clone $employees)->where('active', true)->count();
        $newHires = (clone $employees)->whereBetween('employment_start_date', [$fromDate, $toDate])->count();
        $leavers = (clone $employees)->whereBetween('employment_end_date', [$fromDate, $toDate])->count();
        $byDepartment = (clone $employees)->where('active', true)->selectRaw("COALESCE(NULLIF(department,''),'Unassigned') as label, COUNT(*) as total")
            ->groupBy('department')->orderBy('label')->get();
        $byEmploymentType = (clone $employees)->where('active', true)->selectRaw("COALESCE(NULLIF(employment_type,''),'Unspecified') as label, COUNT(*) as total")
            ->groupBy('employment_type')->orderBy('label')->get();
        $leaveStatuses = DB::table('leave_requests')->where('business_id', $businessId)->whereBetween('created_at', [$fromUtc, $toUtc])
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->orderBy('status')->get();

        $attendance = DB::table('attendance_records as a')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'a.user_id')->where('u.business_id', '=', $businessId);
        })->where('a.business_id', $businessId)->whereBetween('a.clock_in', [$fromUtc, $toUtc])
            ->select('a.clock_in', 'a.clock_out')->get();
        $attendanceHours = 0.0; $openAttendance = 0;
        foreach ($attendance as $record) {
            if (!$record->clock_out) { $openAttendance++; continue; }
            $attendanceHours += max(0, CarbonImmutable::parse($record->clock_in, 'UTC')->diffInSeconds(CarbonImmutable::parse($record->clock_out, 'UTC')) / 3600);
        }

        $rota = DB::table('rota_shifts')->where('business_id', $businessId)->where('status', 'published')
            ->where('starts_at', '<', $toUtc)->where('ends_at', '>', $fromUtc)->get(['starts_at', 'ends_at', 'break_minutes']);
        $rotaHours = 0.0;
        foreach ($rota as $shift) {
            $shiftStart = CarbonImmutable::parse($shift->starts_at, 'UTC')->getTimestamp();
            $shiftEnd = CarbonImmutable::parse($shift->ends_at, 'UTC')->getTimestamp();
            $begin = max($from->getTimestamp(), $shiftStart);
            $finish = min($to->getTimestamp(), $shiftEnd);
            $wholeShiftSeconds = max(1, $shiftEnd - $shiftStart);
            $overlapSeconds = max(0, $finish - $begin);
            $allocatedBreakSeconds = min($overlapSeconds, (int) $shift->break_minutes * 60 * ($overlapSeconds / $wholeShiftSeconds));
            $rotaHours += max(0, ($overlapSeconds - $allocatedBreakSeconds) / 3600);
        }

        $compliance = DB::table('employee_documents')->where('business_id', $businessId)->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$fromDate, $toDate])->where('status', '!=', 'rejected')->count();
        $training = DB::table('employee_training_records')->where('business_id', $businessId)->whereNotNull('expires_on')
            ->whereBetween('expires_on', [$fromDate, $toDate])->count();
        $lifecycle = DB::table('employee_lifecycle_cases')->where('business_id', $businessId)->whereBetween('created_at', [$fromUtc, $toUtc])
            ->select('type', DB::raw('COUNT(*) as total'))->groupBy('type')->orderBy('type')->get();

        return [
            'filters' => ['from' => $fromDate, 'to' => $toDate],
            'summary' => ['active_employees' => $activeCount, 'new_hires' => $newHires, 'leavers' => $leavers,
                'attendance_hours' => round($attendanceHours, 2), 'open_attendance' => $openAttendance,
                'published_rota_hours' => round($rotaHours, 2), 'compliance_expiries' => $compliance, 'training_expiries' => $training],
            'byDepartment' => $byDepartment, 'byEmploymentType' => $byEmploymentType,
            'leaveStatuses' => $leaveStatuses, 'lifecycle' => $lifecycle,
        ];
    }

    public function index(Request $request)
    {
        $report = $this->reportData($request);
        return view('reports.hr', compact('report'));
    }

    public function download(Request $request)
    {
        $this->permissions->require($request->user(), 'reports.export');
        $report = $this->reportData($request);
        $format = $request->validate(['format' => 'nullable|in:csv,pdf'])['format'] ?? 'csv';
        if ($format === 'pdf') {
            return Pdf::loadView('reports.hr-pdf', compact('report'))->setPaper('a4', 'portrait')
                ->download('mentoclock-hr-'.$report['filters']['from'].'-'.$report['filters']['to'].'.pdf');
        }
        $rows = [['Section', 'Measure', 'Value']];
        foreach ($report['summary'] as $key => $value) $rows[] = ['Summary', str_replace('_', ' ', $key), $value];
        foreach (['byDepartment' => 'Department', 'byEmploymentType' => 'Employment type', 'leaveStatuses' => 'Leave status', 'lifecycle' => 'Lifecycle type'] as $key => $section) {
            foreach ($report[$key] as $row) $rows[] = [$section, $row->label ?? $row->status ?? $row->type, $row->total];
        }
        $csv = implode("\r\n", array_map(fn ($row) => implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row)), $rows));
        return response("\xEF\xBB\xBF".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="mentoclock-hr-'.$report['filters']['from'].'-'.$report['filters']['to'].'.csv"']);
    }
}
