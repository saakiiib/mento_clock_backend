<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessCapacity
{
    public function profile(int $businessId): object
    {
        return DB::table('client_profiles')->where('business_id', $businessId)->first()
            ?? (object) ['branch_limit' => 10, 'employees_per_branch_limit' => 10];
    }

    public function activeBranchCount(int $businessId): int
    {
        return DB::table('branches')->where('business_id', $businessId)->where('active', true)->count();
    }

    public function activeEmployeeCount(int $businessId, int $branchId, ?int $excludeUserId = null): int
    {
        $query = DB::table('employee_branches as eb')
            ->join('users as u', function ($join) use ($businessId) {
                $join->on('u.id', '=', 'eb.user_id')->where('u.business_id', '=', $businessId);
            })
            ->where('eb.business_id', $businessId)
            ->where('eb.branch_id', $branchId)
            ->where('u.role', 'employee')
            ->where('u.active', true);
        if ($excludeUserId !== null) $query->where('u.id', '!=', $excludeUserId);
        return $query->count();
    }

    public function employeeLimit(int $businessId, int $branchId): int
    {
        $branch = DB::table('branches')->where('business_id', $businessId)->where('id', $branchId)->first();
        return (int) ($branch?->employee_limit ?: $this->profile($businessId)->employees_per_branch_limit);
    }

    public function ensureBranchSlot(int $businessId): void
    {
        $profile = $this->profile($businessId);
        $limit = (int) $profile->branch_limit;
        $used = $this->activeBranchCount($businessId);
        if ($used >= $limit) {
            throw ValidationException::withMessages([
                'branch' => "You have reached your branch limit ({$used} of {$limit}). Contact Mento Software to increase your limit.",
            ]);
        }
    }

    /** @param array<int, int|string> $branchIds */
    public function ensureEmployeeAssignments(int $businessId, array $branchIds, ?int $employeeId = null): void
    {
        foreach (array_unique(array_map('intval', $branchIds)) as $branchId) {
            $branch = DB::table('branches')->where('business_id', $businessId)->where('id', $branchId)->where('active', true)->first();
            if (!$branch) continue; // The request's branch validation returns the more useful field error.
            $limit = $this->employeeLimit($businessId, $branchId);
            $used = $this->activeEmployeeCount($businessId, $branchId, $employeeId);
            if ($used >= $limit) {
                throw ValidationException::withMessages([
                    'branches' => "{$branch->name} has reached its employee limit ({$used} of {$limit}). Remove an active assignment or contact Mento Software to increase capacity.",
                ]);
            }
        }
    }

    public function ensureCanActivateEmployee(int $businessId, int $employeeId): void
    {
        $branchIds = DB::table('employee_branches')->where('business_id', $businessId)->where('user_id', $employeeId)->pluck('branch_id')->all();
        $this->ensureEmployeeAssignments($businessId, $branchIds, $employeeId);
    }
}
