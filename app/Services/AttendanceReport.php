<?php
namespace App\Services;

use Carbon\CarbonImmutable as C;
use Illuminate\Support\Facades\DB;
use App\Services\LeaveManagement;

class AttendanceReport
{
    public function make(int $businessId, array $filters, ?int $forcedUser = null): array
    {
        $business = DB::table('businesses')->find($businessId);
        $tz = $business->timezone ?: 'UTC';
        $anchor = C::parse($filters['date'] ?? C::now($tz)->toDateString(), $tz)->startOfDay();
        $period = $filters['period'] ?? 'daily';
        if ($period === 'custom') {
            $start = C::parse($filters['start_date'], $tz)->startOfDay();
            $lastDate = C::parse($filters['end_date'], $tz)->startOfDay();
            if ($lastDate->diffInDays($start) > 365) abort(422, 'Custom reports can cover up to 366 days.');
            $end = $lastDate->addDay();
        } else {
            $start = match ($period) {
                'weekly' => $anchor->startOfWeek(C::MONDAY),
                'monthly' => $anchor->startOfMonth(),
                default => $anchor,
            };
            $end = match ($period) {
                'weekly' => $start->addWeek(),
                'monthly' => $start->addMonth(),
                default => $start->addDay(),
            };
        }
        $user = $forcedUser ?? ($filters['employee_id'] ?? null);
        $branch = $filters['branch_id'] ?? null;
        $deductBreaks = filter_var($filters['deduct_breaks'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $q = DB::table('attendance_records as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('branches as b', 'b.id', '=', 'a.branch_id')
            ->where('a.business_id', $businessId)
            ->where('a.clock_in', '<', $end->utc())
            ->where(function ($q) use ($start) {
                $q->whereNull('a.clock_out')->orWhere('a.clock_out', '>', $start->utc());
            });
        if ($user) $q->where('a.user_id', $user);
        if ($branch) $q->where('a.branch_id', $branch);
        $raw = $q->orderBy('a.clock_in')->select('a.*', 'u.name as employee', 'u.employee_code', 'b.name as branch')->get();

        $employeeQuery = DB::table('users as u')->leftJoin('employee_leave_profiles as lp', function ($join) use ($businessId) {
            $join->on('lp.user_id', '=', 'u.id')->where('lp.business_id', '=', $businessId);
        })->where('u.business_id', $businessId)->where('u.role', 'employee');
        if ($user) $employeeQuery->where('u.id', $user);
        if ($branch) $employeeQuery->whereIn('u.id', DB::table('employee_branches')->where('business_id', $businessId)->where('branch_id', $branch)->select('user_id'));
        $employees = $employeeQuery->orderBy('u.name')->get(['u.id', 'u.name', 'u.employee_code', 'lp.holiday_hours_per_day']);
        $employeeSummary = [];
        foreach ($employees as $employee) {
            $employeeSummary[$employee->id] = [
                'employee_id' => (int) $employee->id, 'employee' => $employee->name,
                'employee_code' => $employee->employee_code, 'gross_seconds' => 0, 'break_seconds' => 0,
                'net_seconds' => 0, 'gross_hours' => 0.0, 'break_hours' => 0.0, 'net_hours' => 0.0,
                'finalized_records' => 0, 'open_records' => 0,
                'annual_leave_days' => 0.0, 'annual_leave_hours' => 0.0,
                'holiday_hours_per_day' => $employee->holiday_hours_per_day === null ? null : (float) $employee->holiday_hours_per_day,
            ];
        }

        $days = [];
        $grossTotal = $breakTotal = $active = $finalizedTotal = 0;
        $records = [];
        $now = C::now('UTC');
        foreach ($raw as $row) {
            $in = C::parse($row->clock_in, 'UTC');
            $out = $row->clock_out ? C::parse($row->clock_out, 'UTC') : null;
            $actualEnd = ($out ?? $now)->min($now);
            $actualSeconds = max(0, (int) $in->diffInSeconds($actualEnd));
            $from = $in->max($start->utc());
            $to = $actualEnd->min($end->utc());
            if ($to->lessThanOrEqualTo($from)) continue;
            $grossSeconds = (int) $from->diffInSeconds($to);
            if (!$out) $active++;

            // Breaks come from the employee's rota, not device break punches.
            // Use the best matching published/cancelled historical shift for this
            // branch and cap the allowance at the elapsed attendance duration.
            $breakSeconds = 0;
            if ($actualSeconds > 0) {
                $candidate = DB::table('rota_shifts')
                    ->where('business_id', $businessId)->where('user_id', $row->user_id)
                    ->where('status', '!=', 'draft')
                    ->where('starts_at', '<', $actualEnd->toDateTimeString())
                    ->where('ends_at', '>', $in->toDateTimeString())
                    ->when($row->branch_id, fn ($query) => $query->where('branch_id', $row->branch_id))
                    ->get(['starts_at', 'ends_at', 'break_minutes'])
                    ->map(function ($shift) use ($in, $actualEnd) {
                        $shiftStart = C::parse($shift->starts_at, 'UTC');
                        $shiftEnd = C::parse($shift->ends_at, 'UTC');
                        $overlap = max(0, (int) $shiftStart->max($in)->diffInSeconds($shiftEnd->min($actualEnd)));
                        return ['overlap' => $overlap, 'break' => max(0, (int) $shift->break_minutes) * 60];
                    })->sortByDesc('overlap')->first();
                if ($candidate && $candidate['overlap'] >= min(1800, $actualSeconds)) {
                    $breakSeconds = min($actualSeconds, $candidate['break']);
                }
            }
            // Allocate breaks proportionally when a report clips a shift (for
            // example, an overnight shift in a daily report).
            $periodBreak = min($grossSeconds, (int) round($breakSeconds * $grossSeconds / max(1, $actualSeconds)));
            $grossTotal += $grossSeconds;
            $breakTotal += $periodBreak;
            if (isset($employeeSummary[$row->user_id])) {
                $employeeSummary[$row->user_id]['gross_seconds'] += $grossSeconds;
                $employeeSummary[$row->user_id]['break_seconds'] += $periodBreak;
                $employeeSummary[$row->user_id]['net_seconds'] += max(0, $grossSeconds - $periodBreak);
                if ($row->finalized_at !== null) $employeeSummary[$row->user_id]['finalized_records']++;
                else $employeeSummary[$row->user_id]['open_records']++;
            }
            if ($row->finalized_at !== null) $finalizedTotal++;
            $records[] = [
                'id' => $row->id, 'employee' => $row->employee, 'employee_code' => $row->employee_code,
                'branch' => $row->branch, 'clock_in' => $in->tz($tz)->toIso8601String(),
                'clock_out' => $out?->tz($tz)->toIso8601String(),
                'gross_seconds' => $grossSeconds, 'break_seconds' => $periodBreak,
                'net_seconds' => max(0, $grossSeconds - $periodBreak),
                'gross_hours' => round($grossSeconds / 3600, 2), 'break_hours' => round($periodBreak / 3600, 2),
                'net_hours' => round(max(0, $grossSeconds - $periodBreak) / 3600, 2),
                'seconds' => $deductBreaks ? max(0, $grossSeconds - $periodBreak) : $grossSeconds,
                'hours' => round(($deductBreaks ? max(0, $grossSeconds - $periodBreak) : $grossSeconds) / 3600, 2),
                'finalized_at' => $row->finalized_at,
                'status' => $out ? 'Completed' : 'Active',
            ];
            $cursor = $from;
            while ($cursor->lessThan($to)) {
                $key = $cursor->tz($tz)->toDateString();
                $boundary = $cursor->tz($tz)->startOfDay()->addDay()->utc()->min($to);
                $part = (int) $cursor->diffInSeconds($boundary);
                $partBreak = min($part, (int) round($periodBreak * $part / max(1, $grossSeconds)));
                $days[$key] ??= ['gross' => 0, 'break' => 0];
                $days[$key]['gross'] += $part;
                $days[$key]['break'] += $partBreak;
                $cursor = $boundary;
            }
        }

        $periodStartDate = $start->toDateString();
        $periodEndDate = $end->subDay()->toDateString();
        $leaveQuery = DB::table('leave_requests')->where('business_id', $businessId)->where('leave_type', 'annual')
            ->where('status', 'approved')->where('start_date', '<=', $periodEndDate)->where('end_date', '>=', $periodStartDate);
        if ($user) $leaveQuery->where('user_id', $user);
        if ($branch) $leaveQuery->whereIn('user_id', DB::table('employee_branches')->where('business_id', $businessId)->where('branch_id', $branch)->select('user_id'));
        $leaveCounter = app(LeaveManagement::class);
        foreach ($leaveQuery->get(['user_id', 'start_date', 'end_date']) as $leave) {
            if (!isset($employeeSummary[$leave->user_id])) continue;
            $leaveStart = C::parse($leave->start_date, $tz)->max($start);
            $leaveEnd = C::parse($leave->end_date, $tz)->min($end->subDay());
            if ($leaveEnd->lessThan($leaveStart)) continue;
            $leaveBreakdown = $leaveCounter->countWorkingDays($businessId, (int) $leave->user_id, $leaveStart, $leaveEnd);
            $daysCount = $leaveBreakdown['days'];
            $employeeSummary[$leave->user_id]['annual_leave_days'] += $daysCount;
            $perDay = $employeeSummary[$leave->user_id]['holiday_hours_per_day'];
            if ($perDay !== null) $employeeSummary[$leave->user_id]['annual_leave_hours'] += $daysCount * $perDay;
            foreach ($leaveBreakdown['dates'] as $date) {
                $days[$date] ??= ['gross' => 0, 'break' => 0, 'holiday_days' => 0, 'holiday_hours' => 0.0, 'holiday_incomplete' => false];
                $days[$date]['holiday_days'] ??= 0;
                $days[$date]['holiday_hours'] ??= 0.0;
                $days[$date]['holiday_incomplete'] ??= false;
                $days[$date]['holiday_days'] += 1;
                if ($perDay === null) $days[$date]['holiday_incomplete'] = true;
                else $days[$date]['holiday_hours'] += $perDay;
            }
        }

        $unconfiguredHolidayEmployees = [];
        foreach ($employeeSummary as &$summary) {
            $summary['gross_hours'] = round($summary['gross_seconds'] / 3600, 2);
            $summary['break_hours'] = round($summary['break_seconds'] / 3600, 2);
            $summary['net_hours'] = round($summary['net_seconds'] / 3600, 2);
            $summary['annual_leave_days'] = round($summary['annual_leave_days'], 2);
            $summary['annual_leave_hours'] = $summary['holiday_hours_per_day'] === null && $summary['annual_leave_days'] > 0
                ? null : round($summary['annual_leave_hours'], 2);
            if ($summary['annual_leave_days'] > 0 && $summary['holiday_hours_per_day'] === null) {
                $unconfiguredHolidayEmployees[] = ['employee_id' => $summary['employee_id'], 'employee' => $summary['employee']];
            }
            $summary['payroll_reference_hours'] = $summary['annual_leave_hours'] === null ? null : round($summary['net_hours'] + $summary['annual_leave_hours'], 2);
        }
        unset($summary);
        $employeeSummary = array_values($employeeSummary);
        $leaveDaysTotal = array_sum(array_column($employeeSummary, 'annual_leave_days'));
        $leaveHoursTotal = array_sum(array_map(fn ($item) => (float) ($item['annual_leave_hours'] ?? 0), $employeeSummary));
        $holidayHoursComplete = count($unconfiguredHolidayEmployees) === 0;

        $daily = [];
        for ($d = $start; $d->lessThan($end); $d = $d->addDay()) {
            $key = $d->toDateString();
            $gross = $days[$key]['gross'] ?? 0;
            $break = min($gross, $days[$key]['break'] ?? 0);
            $net = max(0, $gross - $break);
            $selected = $deductBreaks ? $net : $gross;
            $holidayHours = $days[$key]['holiday_incomplete'] ?? false ? null : round($days[$key]['holiday_hours'] ?? 0, 2);
            $daily[] = ['date' => $key, 'gross_seconds' => $gross, 'break_seconds' => $break, 'net_seconds' => $net,
                'seconds' => $selected, 'gross_hours' => round($gross / 3600, 2), 'break_hours' => round($break / 3600, 2),
                'net_hours' => round($net / 3600, 2), 'hours' => round($selected / 3600, 2),
                'annual_leave_days' => (float) ($days[$key]['holiday_days'] ?? 0), 'annual_leave_hours' => $holidayHours,
                'payroll_reference_hours' => $holidayHours === null ? null : round(($net / 3600) + $holidayHours, 2)];
        }
        $netTotal = max(0, $grossTotal - $breakTotal);
        $selectedTotal = $deductBreaks ? $netTotal : $grossTotal;
        return [
            'business' => $business->name, 'timezone' => $tz, 'period' => $period,
            'start' => $start->toDateString(), 'end' => $end->subDay()->toDateString(),
            'deduct_breaks' => $deductBreaks,
            'total_gross_seconds' => $grossTotal, 'total_break_seconds' => $breakTotal, 'total_net_seconds' => $netTotal,
            'total_gross_hours' => round($grossTotal / 3600, 2), 'total_break_hours' => round($breakTotal / 3600, 2),
            'total_net_hours' => round($netTotal / 3600, 2), 'total_seconds' => $selectedTotal,
            'total_hours' => round($selectedTotal / 3600, 2), 'active_records' => $active,
            'total_finalized_records' => $finalizedTotal,
            'total_annual_leave_days' => round($leaveDaysTotal, 2), 'total_annual_leave_hours' => round($leaveHoursTotal, 2),
            'holiday_hours_complete' => $holidayHoursComplete, 'unconfigured_holiday_employees' => $unconfiguredHolidayEmployees,
            'total_payroll_reference_hours' => $holidayHoursComplete ? round(($netTotal / 3600) + $leaveHoursTotal, 2) : null,
            'employee_summaries' => $employeeSummary,
            'record_count' => count($records), 'daily' => $daily, 'records' => $records,
        ];
    }

    public function csv(array $report): string
    {
        $f = fopen('php://temp', 'r+');
        $safe = fn ($v) => preg_match('/^[\s]*[=+@\-]/u', (string) $v) ? "'".$v : $v;
        fputcsv($f, array_map($safe, ['Business', $report['business'], 'Timezone', $report['timezone'], 'Period', $report['start'].' to '.$report['end']]));
        fputcsv($f, ['Employee', 'Employee code', 'Branch', 'Clock in', 'Clock out', 'Gross hours', 'Unpaid break hours', 'Net hours', 'Report hours', 'Payroll review']);
        foreach ($report['records'] as $r) {
            $values = [$r['employee'], $r['employee_code'] ?? '', $r['branch'], $r['clock_in'], $r['clock_out'] ?? '',
                (string) $r['gross_hours'], (string) $r['break_hours'], (string) $r['net_hours'], (string) $r['hours'], $r['finalized_at'] !== null ? 'Finalized' : ($r['clock_out'] === null ? 'In progress' : 'Needs review')];
            fputcsv($f, array_map($safe, $values));
        }
        fputcsv($f, []);
        fputcsv($f, ['Employee payroll reference summary']);
        fputcsv($f, ['Employee', 'Employee code', 'Gross worked hours', 'Unpaid break hours', 'Net worked hours', 'Approved annual leave days', 'Paid holiday hours', 'Payroll reference hours', 'Finalized records', 'Needs review']);
        foreach ($report['employee_summaries'] as $row) {
            $values = [$row['employee'], $row['employee_code'] ?? '', (string) $row['gross_hours'], (string) $row['break_hours'],
                (string) $row['net_hours'], (string) $row['annual_leave_days'],
                $row['annual_leave_hours'] === null ? 'Not configured' : (string) $row['annual_leave_hours'],
                $row['payroll_reference_hours'] === null ? 'Incomplete' : (string) $row['payroll_reference_hours'],
                (string) $row['finalized_records'], (string) $row['open_records']];
            fputcsv($f, array_map($safe, $values));
        }
        rewind($f); $csv = stream_get_contents($f); fclose($f); return $csv;
    }
}
