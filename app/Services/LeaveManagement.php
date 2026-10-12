<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveManagement
{
    public const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    public const REGIONS = ['all', 'england_wales', 'scotland', 'northern_ireland', 'local'];
    public const TYPES = ['annual', 'sick', 'unpaid', 'compassionate', 'other'];

    public function policy(int $businessId): object
    {
        return DB::table('business_leave_policies')->where('business_id', $businessId)->first() ?? (object) [
            'business_id' => $businessId,
            'leave_year_start_month' => 1,
            'leave_year_start_day' => 1,
            'default_annual_entitlement' => 0,
            'holiday_region' => 'england_wales',
        ];
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable} */
    public function yearFor(int $businessId, CarbonImmutable $date): array
    {
        $policy = $this->policy($businessId);
        $month = (int) $policy->leave_year_start_month;
        $day = (int) $policy->leave_year_start_day;
        $year = $date->month > $month || ($date->month === $month && $date->day >= $day)
            ? $date->year : $date->year - 1;

        $start = CarbonImmutable::create($year, $month, $day)->startOfDay();
        return ['start' => $start, 'end' => $start->addYear()->subDay()->endOfDay()];
    }

    /** @return array{days: float, dates: array<int, string>} */
    public function countWorkingDays(int $businessId, int $userId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $profile = DB::table('employee_leave_profiles')->where('business_id', $businessId)->where('user_id', $userId)->first();
        $workingDays = $profile && $profile->working_days
            ? json_decode($profile->working_days, true)
            : ['mon', 'tue', 'wed', 'thu', 'fri'];
        if (!is_array($workingDays)) $workingDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
        $workingDays = array_values(array_intersect(self::WEEKDAYS, $workingDays));

        $region = $this->policy($businessId)->holiday_region;
        $holidayDates = DB::table('business_holidays')->where('business_id', $businessId)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('region', ['all', 'local', $region])->pluck('holiday_date')
            ->map(fn ($date) => (string) $date)->all();
        $holidayLookup = array_fill_keys($holidayDates, true);

        $dates = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $weekday = strtolower($date->format('D'));
            if (in_array($weekday, $workingDays, true) && !isset($holidayLookup[$date->toDateString()])) {
                $dates[] = $date->toDateString();
            }
        }

        return ['days' => (float) count($dates), 'dates' => $dates];
    }

    public function entitlement(int $businessId, int $userId): float
    {
        $profile = DB::table('employee_leave_profiles')->where('business_id', $businessId)->where('user_id', $userId)->first();
        return (float) ($profile?->annual_entitlement_override ?? $this->policy($businessId)->default_annual_entitlement);
    }

    /** @return array{year_start: string, year_end: string, entitlement: float, used: float, pending: float, remaining: float} */
    public function balance(int $businessId, int $userId, ?CarbonImmutable $forDate = null, ?int $exceptRequestId = null): array
    {
        $period = $this->yearFor($businessId, $forDate ?? CarbonImmutable::now('UTC'));
        $requests = DB::table('leave_requests')->where('business_id', $businessId)->where('user_id', $userId)
            ->where('leave_type', 'annual')->whereBetween('start_date', [$period['start']->toDateString(), $period['end']->toDateString()]);
        if ($exceptRequestId) $requests->where('id', '!=', $exceptRequestId);
        $used = (float) (clone $requests)->where('status', 'approved')->sum('working_days');
        $pending = (float) (clone $requests)->where('status', 'pending')->sum('working_days');
        $entitlement = $this->entitlement($businessId, $userId);

        return [
            'year_start' => $period['start']->toDateString(),
            'year_end' => $period['end']->toDateString(),
            'entitlement' => $entitlement,
            'used' => $used,
            'pending' => $pending,
            'remaining' => max(0, $entitlement - $used - $pending),
        ];
    }

    public function assertWithinLeaveYear(int $businessId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $period = $this->yearFor($businessId, $start);
        if ($end->gt($period['end'])) {
            throw ValidationException::withMessages(['end_date' => 'Please split this request at the leave-year boundary and submit each part separately.']);
        }
    }

    public function assertNoOverlap(int $businessId, int $userId, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptId = null): void
    {
        $query = DB::table('leave_requests')->where('business_id', $businessId)->where('user_id', $userId)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());
        if ($exceptId) $query->where('id', '!=', $exceptId);
        if ($query->exists()) {
            throw ValidationException::withMessages(['start_date' => 'This request overlaps another pending or approved leave request.']);
        }
    }
}
