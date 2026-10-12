<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RotaPlanner
{
    public function assertLeaveCanBeApproved(int $businessId, int $employeeId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $fromUtc = $start->startOfDay()->utc()->toDateTimeString();
        $untilUtc = $end->endOfDay()->utc()->toDateTimeString();
        $shift = DB::table('rota_shifts')->where('business_id', $businessId)->where('user_id', $employeeId)
            ->whereIn('status', ['draft', 'published'])
            ->where('starts_at', '<', $untilUtc)->where('ends_at', '>', $fromUtc)->first();
        if ($shift) {
            throw ValidationException::withMessages(['decision' => 'This employee has a draft or published rota shift during the requested leave. Cancel or move the shift, then review the leave request again.']);
        }
    }

    /** Return a user-facing conflict or null when this shift can be placed. */
    public function conflict(int $businessId, int $employeeId, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptShift = null): ?string
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $leave = DB::table('leave_requests')->where('business_id', $businessId)->where('user_id', $employeeId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', $endDate)->whereDate('end_date', '>=', $startDate)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")->first();
        if ($leave) {
            return $leave->status === 'approved'
                ? 'This employee has approved leave during these dates. Cancel or change the leave request before scheduling a shift.'
                : 'This employee has a pending leave request during these dates. Resolve the request before scheduling a shift.';
        }

        $unavailable = DB::table('rota_availability')->where('business_id', $businessId)->where('user_id', $employeeId)
            ->where('availability', 'unavailable')->where('starts_at', '<', $end->utc()->toDateTimeString())
            ->where('ends_at', '>', $start->utc()->toDateTimeString())->exists();
        if ($unavailable) return 'This employee is marked unavailable during these shift hours.';

        $availableOverlap = DB::table('rota_availability')->where('business_id', $businessId)->where('user_id', $employeeId)
            ->where('availability', 'available')->where('starts_at', '<', $end->utc()->toDateTimeString())
            ->where('ends_at', '>', $start->utc()->toDateTimeString())->exists();
        if ($availableOverlap) {
            $fullyAvailable = DB::table('rota_availability')->where('business_id', $businessId)->where('user_id', $employeeId)
                ->where('availability', 'available')->where('starts_at', '<=', $start->utc()->toDateTimeString())
                ->where('ends_at', '>=', $end->utc()->toDateTimeString())->exists();
            if (!$fullyAvailable) return 'This shift falls outside the employee’s recorded available hours.';
        }

        $policy = DB::table('business_leave_policies')->where('business_id', $businessId)->first();
        $region = $policy?->holiday_region ?? 'england_wales';
        $closure = DB::table('business_holidays')->where('business_id', $businessId)->where('is_closure', true)
            ->whereIn('region', ['all', 'local', $region])->whereBetween('holiday_date', [$startDate, $endDate])->first();
        if ($closure) return 'The business is marked closed for '.$closure->name.' ('.$closure->holiday_date.').';

        $overlap = DB::table('rota_shifts')->where('business_id', $businessId)->where('user_id', $employeeId)
            ->whereIn('status', ['draft', 'published'])->where('starts_at', '<', $end->utc()->toDateTimeString())
            ->where('ends_at', '>', $start->utc()->toDateTimeString());
        if ($exceptShift) $overlap->where('id', '!=', $exceptShift);
        if ($overlap->exists()) return 'This shift overlaps another draft or published shift for the employee.';

        return null;
    }

    public function assertNoConflict(int $businessId, int $employeeId, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptShift = null): void
    {
        if ($message = $this->conflict($businessId, $employeeId, $start, $end, $exceptShift)) {
            throw ValidationException::withMessages(['starts_at' => $message]);
        }
    }
}
