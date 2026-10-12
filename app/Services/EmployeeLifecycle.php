<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EmployeeLifecycle
{
    public const CHECKLISTS = [
        'onboarding' => [
            ['Confirm signed employment contract', 'employment'],
            ['Complete right-to-work evidence review', 'compliance'],
            ['Collect payroll and bank details', 'payroll'],
            ['Confirm emergency contact details', 'people'],
            ['Assign workplace, department and reporting manager', 'people'],
            ['Provide handbook, policies and required notices', 'documents'],
            ['Schedule role-specific induction and training', 'training'],
            ['Issue equipment and record asset handover', 'assets'],
        ],
        'offboarding' => [
            ['Confirm notice and final working date', 'employment'],
            ['Plan handover and transfer responsibilities', 'handover'],
            ['Recover keys, equipment and other business assets', 'assets'],
            ['Revoke account and workplace access on the leaving date', 'security'],
            ['Review outstanding leave and final payroll actions', 'payroll'],
            ['Collect exit feedback and complete exit interview', 'people'],
            ['File final documents and update employee record', 'documents'],
        ],
    ];

    public function open(int $businessId, int $userId, string $type, ?string $effectiveDate, ?int $actorId): int
    {
        $caseId = DB::table('employee_lifecycle_cases')->insertGetId([
            'business_id' => $businessId, 'user_id' => $userId, 'type' => $type,
            'status' => 'in_progress', 'effective_date' => $effectiveDate,
            'created_by' => $actorId, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        foreach (self::CHECKLISTS[$type] as [$title, $category]) {
            DB::table('employee_lifecycle_tasks')->insert([
                'business_id' => $businessId, 'case_id' => $caseId, 'title' => $title,
                'category' => $category, 'status' => 'pending', 'due_date' => $effectiveDate,
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
        }
        DB::table('hr_module_events')->insert([
            'business_id' => $businessId, 'actor_id' => $actorId, 'subject_type' => 'lifecycle_case',
            'subject_id' => $caseId, 'action' => $type.'_started',
            'details' => json_encode(['user_id' => $userId], JSON_THROW_ON_ERROR), 'created_at' => now('UTC'),
        ]);
        return $caseId;
    }

    public function processDueOffboarding(): int
    {
        $processed = 0;
        $cases = DB::table('employee_lifecycle_cases as c')->join('businesses as b', 'b.id', '=', 'c.business_id')
            ->where('c.type', 'offboarding')->where('c.status', 'scheduled')->whereNotNull('c.effective_date')
            ->select('c.id', 'c.business_id', 'c.user_id', 'c.effective_date', 'b.timezone')->get();
        foreach ($cases as $candidate) {
            if ($candidate->effective_date > now($candidate->timezone ?: 'UTC')->toDateString()) continue;
            DB::transaction(function () use ($candidate, &$processed) {
                $case = DB::table('employee_lifecycle_cases')->where('business_id', $candidate->business_id)->where('id', $candidate->id)->lockForUpdate()->first();
                if (!$case || $case->status !== 'scheduled') return;
                $now = now('UTC');
                DB::table('users')->where('business_id', $case->business_id)->where('id', $case->user_id)->update([
                    'active' => false, 'employment_end_date' => $case->effective_date, 'updated_at' => $now,
                ]);
                DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->where('tokenable_id', $case->user_id)->delete();
                DB::table('sessions')->where('user_id', $case->user_id)->delete();
                DB::table('employee_lifecycle_cases')->where('id', $case->id)->update(['status' => 'complete', 'completed_at' => $now, 'updated_at' => $now]);
                DB::table('hr_module_events')->insert([
                    'business_id' => $case->business_id, 'actor_id' => null, 'subject_type' => 'lifecycle_case',
                    'subject_id' => $case->id, 'action' => 'offboarding_effective', 'details' => null, 'created_at' => $now,
                ]);
                $processed++;
            });
        }
        return $processed;
    }
}
