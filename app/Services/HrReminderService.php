<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class HrReminderService
{
    public function __construct(private ModuleAccess $modules, private WorkspacePermissions $permissions) {}

    public function run(): int
    {
        $created = 0;
        foreach (DB::table('businesses')->where('active', true)->get(['id', 'timezone']) as $business) {
            $businessId = (int) $business->id;
            if (!$this->modules->enabled($businessId, 'hr_reminders')) continue;
            $today = CarbonImmutable::now($business->timezone ?: 'UTC')->toDateString();
            $created += $this->createExpiryReminders($businessId, $today, 'employee_documents', 'expiry_date', 'employee_document', 'Compliance document expiry', 'document');
            $created += $this->createExpiryReminders($businessId, $today, 'employee_training_records', 'expires_on', 'training_record', 'Training certificate expiry', 'training');
            $created += $this->createEmployeeExpiryReminders($businessId, $today);
            $created += $this->createOverdueTaskReminders($businessId, $today);
            $created += $this->createPendingLeaveReminders($businessId, $today);
            $this->emailDigest($businessId, $today);
        }
        return $created;
    }

    private function insert(int $businessId, string $type, string $subjectType, int $subjectId, string $title, string $body, string $date): bool
    {
        $exists = DB::table('hr_reminders')->where('business_id', $businessId)->where('reminder_type', $type)
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId)->where('remind_on', $date)->exists();
        if ($exists) return false;
        $inserted = DB::table('hr_reminders')->insertOrIgnore([
            'business_id' => $businessId, 'reminder_type' => $type, 'subject_type' => $subjectType,
            'subject_id' => $subjectId, 'title' => $title, 'body' => $body, 'remind_on' => $date,
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        return $inserted > 0;
    }

    private function createExpiryReminders(int $businessId, string $today, string $table, string $dateColumn, string $subjectType, string $label, string $kind): int
    {
        $cutoff = CarbonImmutable::parse($today)->addDays(30)->toDateString();
        $query = DB::table($table.' as r')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'r.user_id')->where('u.business_id', '=', $businessId);
        })->where('r.business_id', $businessId)->whereNotNull('r.'.$dateColumn)->where('r.'.$dateColumn, '<=', $cutoff);
        if ($kind === 'document') $query->where('r.status', '!=', 'rejected');
        $items = $query->select('r.id', 'r.'.$dateColumn.' as expires_on', 'u.name')->get();
        $created = 0;
        foreach ($items as $item) {
            $expires = CarbonImmutable::parse($item->expires_on);
            $days = (int) CarbonImmutable::parse($today)->diffInDays($expires, false);
            if (!in_array($days, [30, 14, 7, 1, 0], true) && $days >= 0) continue;
            $description = $kind === 'document' ? 'a compliance document' : 'a training record';
            $when = $days < 0 ? abs($days).' day(s) overdue' : ($days === 0 ? 'expires today' : 'expires in '.$days.' day(s)');
            $created += (int) $this->insert($businessId, $kind.'_expiry', $subjectType, (int) $item->id, $label, $item->name.' has '.$description.' that '.$when.'.', $days < 0 ? $expires->toDateString() : $today);
        }
        return $created;
    }

    private function createEmployeeExpiryReminders(int $businessId, string $today): int
    {
        $fields = ['contract_end_date' => 'Employment contract expiry', 'visa_expiry_date' => 'Visa expiry', 'passport_expiry_date' => 'Passport expiry', 'cos_end_date' => 'Certificate of Sponsorship expiry'];
        $cutoff = CarbonImmutable::parse($today)->addDays(30)->toDateString();
        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)->get(['id', 'name', ...array_keys($fields)]);
        $created = 0;
        foreach ($employees as $employee) foreach ($fields as $column => $label) {
            if (!$employee->{$column} || $employee->{$column} > $cutoff) continue;
            $expires = CarbonImmutable::parse($employee->{$column});
            $days = (int) CarbonImmutable::parse($today)->diffInDays($expires, false);
            if (!in_array($days, [30, 14, 7, 1, 0], true) && $days >= 0) continue;
            $when = $days < 0 ? abs($days).' day(s) overdue' : ($days === 0 ? 'expires today' : 'expires in '.$days.' day(s)');
            $created += (int) $this->insert($businessId, strtolower(str_replace(' ', '_', $label)), 'employee', (int) $employee->id, $label, $employee->name.' has a record that '.$when.'.', $days < 0 ? $expires->toDateString() : $today);
        }
        return $created;
    }

    private function createOverdueTaskReminders(int $businessId, string $today): int
    {
        $tasks = DB::table('employee_lifecycle_tasks as t')->join('employee_lifecycle_cases as c', function ($join) use ($businessId) {
            $join->on('c.id', '=', 't.case_id')->where('c.business_id', '=', $businessId);
        })->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'c.user_id')->where('u.business_id', '=', $businessId);
        })->where('t.business_id', $businessId)->where('t.status', '!=', 'complete')->whereNotNull('t.due_date')->where('t.due_date', '<', $today)
            ->select('t.id', 't.title', 't.due_date', 'u.name')->get();
        $created = 0;
        foreach ($tasks as $task) $created += (int) $this->insert($businessId, 'lifecycle_task_overdue', 'lifecycle_task', (int) $task->id,
            'Overdue employee workflow task', $task->title.' for '.$task->name.' was due on '.$task->due_date.'.', $task->due_date);
        return $created;
    }

    private function createPendingLeaveReminders(int $businessId, string $today): int
    {
        $cutoff = now('UTC')->subDays(2)->toDateTimeString();
        $items = DB::table('leave_requests as l')->join('users as u', function ($join) use ($businessId) {
            $join->on('u.id', '=', 'l.user_id')->where('u.business_id', '=', $businessId);
        })->where('l.business_id', $businessId)->where('l.status', 'pending')->where('l.created_at', '<=', $cutoff)
            ->select('l.id', 'l.created_at', 'u.name')->get();
        $created = 0;
        foreach ($items as $item) {
            $remindOn = CarbonImmutable::parse($item->created_at, 'UTC')->addDays(2)->toDateString();
            $created += (int) $this->insert($businessId, 'leave_request_pending', 'leave_request', (int) $item->id,
                'Leave request awaiting review', $item->name.' has a leave request waiting for more than two days.', $remindOn);
        }
        return $created;
    }

    private function emailDigest(int $businessId, string $today): void
    {
        $pending = DB::table('hr_reminders')->where('business_id', $businessId)->whereNull('email_sent_at')->where('remind_on', '<=', $today)->orderBy('remind_on')->limit(100)->get();
        if ($pending->isEmpty()) return;
        $recipients = User::where('business_id', $businessId)->where('active', true)->get()->filter(
            fn (User $user) => $this->permissions->has($user, 'reminders.view')
        );
        if ($recipients->isEmpty()) return;
        $lines = $pending->map(fn ($item) => '- '.$item->title.': '.$item->body)->all();
        $message = "MentoClock HR reminders\n\n".implode("\n", $lines)."\n\nSign in to your client portal to review these items.";
        $sent = false;
        foreach ($recipients as $recipient) {
            try { Mail::raw($message, fn ($mail) => $mail->to($recipient->email)->subject('MentoClock HR reminders')); $sent = true; }
            catch (Throwable) { /* Keep reminders queued for a later scheduler run. */ }
        }
        if ($sent) DB::table('hr_reminders')->whereIn('id', $pending->pluck('id'))->whereNull('email_sent_at')->update(['email_sent_at' => now('UTC'), 'updated_at' => now('UTC')]);
    }
}
