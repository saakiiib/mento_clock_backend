<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BusinessCapacity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Password};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformSupportController
{
    public function __construct(private readonly BusinessCapacity $capacity) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'business_id' => 'nullable|integer|exists:businesses,id',
        ]);
        $query = DB::table('users as u')->join('businesses as b', 'b.id', '=', 'u.business_id')
            ->where('u.role', 'employee');
        if (!empty($filters['q'])) {
            $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($filters['q']));
            $query->where(fn ($q) => $q->where('u.name', 'like', '%'.$needle.'%')
                ->orWhere('u.email', 'like', '%'.$needle.'%')->orWhere('b.name', 'like', '%'.$needle.'%'));
        }
        if (!empty($filters['business_id'])) $query->where('u.business_id', $filters['business_id']);

        $employees = $query->select('u.id', 'u.business_id', 'u.name', 'u.email', 'u.employee_code', 'u.active', 'b.name as business_name', 'b.active as business_active')
            ->selectSub(DB::table('attendance_records as a')->selectRaw('COUNT(*)')->whereColumn('a.user_id', 'u.id')->whereNull('a.clock_out'), 'open_shifts')
            ->orderBy('b.name')->orderBy('u.name')->paginate(25)->withQueryString();
        $businesses = DB::table('businesses')->orderBy('name')->get(['id', 'name']);
        return view('platform.support.index', compact('employees', 'businesses', 'filters'));
    }

    public function employee(int $employeeId)
    {
        $employee = User::where('role', 'employee')->findOrFail($employeeId);
        $business = DB::table('businesses')->where('id', $employee->business_id)->firstOrFail();
        // Older employee rows may not have client_account_id set. The owning
        // business is the source of truth for the parent client account.
        $clientAccountId = $business->client_account_id ? (int) $business->client_account_id : null;
        $branches = DB::table('employee_branches as eb')->join('branches as b', function ($join) use ($employee) {
            $join->on('b.id', '=', 'eb.branch_id')->where('b.business_id', '=', $employee->business_id);
        })->where('eb.business_id', $employee->business_id)->where('eb.user_id', $employee->id)
            ->orderBy('b.name')->get(['b.id', 'b.name', 'b.active']);
        $records = DB::table('attendance_records as a')->join('branches as b', 'b.id', '=', 'a.branch_id')
            ->where('a.business_id', $employee->business_id)->where('a.user_id', $employee->id)
            ->orderByDesc('a.clock_in')->limit(25)->get(['a.*', 'b.name as branch_name']);
        $destinationBusinesses = collect();
        $destinationBranches = collect();
        if ($clientAccountId) {
            $destinationBusinesses = DB::table('businesses')->where('client_account_id', $clientAccountId)
                ->where('active', true)->where('id', '!=', $employee->business_id)->orderBy('name')->get(['id', 'name']);
            $destinationBranches = DB::table('branches')->whereIn('business_id', $destinationBusinesses->pluck('id'))
                ->where('active', true)->orderBy('name')->get(['id', 'business_id', 'name']);
        }
        return view('platform.support.employee', compact('employee', 'business', 'branches', 'records', 'destinationBusinesses', 'destinationBranches'));
    }

    /** Transfer access while keeping the source business's historical records intact. */
    public function transferEmployee(Request $request, int $employeeId)
    {
        $employee = User::where('role', 'employee')->findOrFail($employeeId);
        $sourceBusinessId = (int) DB::table('users')->where('id', $employee->id)->value('business_id');
        $clientAccountId = DB::table('businesses')->where('id', $sourceBusinessId)->value('client_account_id');
        abort_unless($clientAccountId, 422, 'This employee’s business is not linked to a client account.');
        $clientAccountId = (int) $clientAccountId;
        $values = $request->validate([
            'destination_business_id' => ['required', 'integer', Rule::exists('businesses', 'id')->where(fn ($query) =>
                $query->where('client_account_id', $clientAccountId)->where('active', true))],
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => ['required', 'integer', Rule::exists('branches', 'id')->where('business_id', (int) $request->input('destination_business_id'))->where('active', true)],
            'client_contact' => 'required|string|min:2|max:120',
            'client_confirmed' => 'accepted',
        ]);
        $destinationBusinessId = (int) $values['destination_business_id'];
        abort_if($sourceBusinessId === $destinationBusinessId, 422, 'Choose a different business in the same client account.');
        $branchIds = array_values(array_unique(array_map('intval', $values['branch_ids'])));
        $oldEmail = $employee->email;
        $destinationUserId = DB::transaction(function () use ($employee, $sourceBusinessId, $destinationBusinessId, $branchIds, $values, $oldEmail, $clientAccountId) {
            $lockedBusinesses = DB::table('businesses')->whereIn('id', [$sourceBusinessId, $destinationBusinessId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($lockedBusinesses->count() === 2
                && (int) $lockedBusinesses[$sourceBusinessId]->client_account_id === $clientAccountId
                && (int) $lockedBusinesses[$destinationBusinessId]->client_account_id === $clientAccountId
                && (bool) $lockedBusinesses[$destinationBusinessId]->active,
                422, 'Both businesses must remain active members of the same client account.');
            $activeDestinationBranches = DB::table('branches')->where('business_id', $destinationBusinessId)
                ->where('active', true)->whereIn('id', $branchIds)->count();
            abort_unless($activeDestinationBranches === count($branchIds), 422, 'Choose active branches that belong to the destination business.');
            $current = User::where('business_id', $sourceBusinessId)->where('role', 'employee')->where('id', $employee->id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('attendance_records')->where('business_id', $sourceBusinessId)->where('user_id', $current->id)->whereNull('clock_out')->exists(), 422, 'Clock out or correct the employee’s open shift before transferring this account.');
            if ($current->active) $this->capacity->ensureEmployeeAssignments($destinationBusinessId, $branchIds);

            // Attendance has a composite foreign key to (user_id, business_id).
            // Keep the old user row as a disabled, non-login history record, then
            // create a destination account with the same email and password hash.
            $archiveEmail = 'transferred-'.$current->id.'@archive.mentoclock.invalid';
            abort_if(DB::table('users')->where('email', $archiveEmail)->where('id', '!=', $current->id)->exists(), 409, 'The archive email is already in use. Contact support.');
            $raw = (array) DB::table('users')->where('id', $current->id)->first();
            unset($raw['id']);
            $now = now('UTC');
            DB::table('users')->where('id', $current->id)->update([
                'client_account_id' => $clientAccountId,
                'email' => $archiveEmail, 'active' => false, 'remember_token' => null, 'updated_at' => $now,
            ]);
            $newUser = array_replace($raw, [
                'business_id' => $destinationBusinessId,
                'client_account_id' => $clientAccountId,
                'business_role_id' => null,
                'email' => $oldEmail,
                'active' => (bool) $current->active,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newUserId = (int) DB::table('users')->insertGetId($newUser);
            foreach ($branchIds as $branchId) {
                DB::table('employee_branches')->insert([
                    'business_id' => $destinationBusinessId, 'user_id' => $newUserId, 'branch_id' => $branchId,
                ]);
            }
            $current->tokens()->delete();
            DB::table('sessions')->where('user_id', $current->id)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $current->id)->delete();

            $this->audit($sourceBusinessId, 'Employee transferred to another business', [
                'employee_id' => $current->id, 'destination_employee_id' => $newUserId,
                'email' => $oldEmail, 'destination_business_id' => $destinationBusinessId,
                'client_contact' => trim($values['client_contact']), 'history_retained_in_source' => true,
            ]);
            $this->audit($destinationBusinessId, 'Employee received from another business', [
                'source_employee_id' => $current->id, 'employee_id' => $newUserId,
                'email' => $oldEmail, 'source_business_id' => $sourceBusinessId,
                'client_contact' => trim($values['client_contact']), 'history_retained_in_source' => true,
            ]);
            return $newUserId;
        });

        return redirect('/platform/support/employees/'.$destinationUserId)
            ->with('status', 'Employee access transferred. The original login email and password are retained; past records remain with the source business. The employee may need to sign in again.');
    }

    /** Permanently remove employee operational data after explicit email confirmation. */
    public function deleteEmployee(Request $request, int $employeeId)
    {
        $employee = User::where('role', 'employee')->findOrFail($employeeId);
        $values = $request->validate([
            'confirm_email' => 'required|string|max:255',
            'client_contact' => 'required|string|min:2|max:120',
            'client_confirmed' => 'accepted',
        ]);
        abort_unless(hash_equals(mb_strtolower($employee->email), mb_strtolower(trim($values['confirm_email']))), 422, 'Type the employee’s login email exactly to confirm deletion.');
        $businessId = (int) DB::table('users')->where('id', $employee->id)->value('business_id');
        $documentPaths = [];

        DB::transaction(function () use ($employee, $businessId, $values, &$documentPaths) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $current = User::where('business_id', $businessId)->where('role', 'employee')->where('id', $employee->id)->lockForUpdate()->firstOrFail();
            $attendanceIds = DB::table('attendance_records')->where('business_id', $businessId)->where('user_id', $current->id)->pluck('id');
            $shiftIds = DB::table('rota_shifts')->where('business_id', $businessId)->where('user_id', $current->id)->pluck('id');
            $leaveIds = DB::table('leave_requests')->where('business_id', $businessId)->where('user_id', $current->id)->pluck('id');
            $caseIds = DB::table('employee_lifecycle_cases')->where('business_id', $businessId)->where('user_id', $current->id)->pluck('id');
            $taskIds = $caseIds->isEmpty() ? collect() : DB::table('employee_lifecycle_tasks')->whereIn('case_id', $caseIds)->pluck('id');
            $documentIds = DB::table('employee_documents')->where('business_id', $businessId)->where('user_id', $current->id)->pluck('id');
            $trainingIds = DB::table('employee_training_records')->where('business_id', $businessId)->where('user_id', $current->id)->pluck('id');
            $documentPaths = DB::table('employee_documents')->where('business_id', $businessId)->where('user_id', $current->id)->pluck('storage_path')->all();
            $targetedNoticeIds = DB::table('company_notices')->where('business_id', $businessId)->where('audience', 'employee')->where('target_id', $current->id)->pluck('id');

            if ($attendanceIds->isNotEmpty()) {
                DB::table('audit_logs')->whereIn('attendance_id', $attendanceIds)->delete();
            }
            DB::table('audit_logs')->where('actor_id', $current->id)->delete();
            if ($leaveIds->isNotEmpty()) {
                DB::table('leave_request_events')->whereIn('leave_request_id', $leaveIds)->delete();
                DB::table('leave_requests')->whereIn('id', $leaveIds)->delete();
            }
            DB::table('leave_admin_events')->where('business_id', $businessId)->where('actor_id', $current->id)->delete();
            DB::table('leave_admin_events')->where('business_id', $businessId)->where('subject_type', 'employee_leave_profile')->where('subject_id', $current->id)->delete();
            if ($leaveIds->isNotEmpty()) DB::table('leave_admin_events')->where('business_id', $businessId)->where('subject_type', 'leave_request')->whereIn('subject_id', $leaveIds)->delete();
            DB::table('employee_leave_profiles')->where('business_id', $businessId)->where('user_id', $current->id)->delete();
            if ($shiftIds->isNotEmpty()) {
                DB::table('rota_events')->whereIn('rota_shift_id', $shiftIds)->delete();
                DB::table('rota_shifts')->whereIn('id', $shiftIds)->delete();
            }
            DB::table('rota_events')->where('business_id', $businessId)->where('actor_id', $current->id)->delete();
            DB::table('rota_availability')->where('business_id', $businessId)->where('user_id', $current->id)->delete();
            DB::table('attendance_records')->where('business_id', $businessId)->where('user_id', $current->id)->delete();
            DB::table('employee_documents')->where('business_id', $businessId)->where('user_id', $current->id)->delete();
            DB::table('employee_training_records')->where('business_id', $businessId)->where('user_id', $current->id)->delete();
            DB::table('company_notice_receipts')->where('business_id', $businessId)->where('user_id', $current->id)->delete();
            if ($targetedNoticeIds->isNotEmpty()) {
                DB::table('company_notice_receipts')->whereIn('notice_id', $targetedNoticeIds)->delete();
                DB::table('company_notices')->whereIn('id', $targetedNoticeIds)->delete();
            }
            if ($caseIds->isNotEmpty()) DB::table('employee_lifecycle_cases')->whereIn('id', $caseIds)->delete();
            DB::table('hr_reminders')->where('business_id', $businessId)->where('subject_id', $current->id)->whereIn('subject_type', ['user', 'employee'])->delete();
            if ($leaveIds->isNotEmpty()) DB::table('hr_reminders')->where('business_id', $businessId)->where('subject_type', 'leave_request')->whereIn('subject_id', $leaveIds)->delete();
            if ($documentIds->isNotEmpty()) DB::table('hr_reminders')->where('business_id', $businessId)->where('subject_type', 'employee_document')->whereIn('subject_id', $documentIds)->delete();
            if ($trainingIds->isNotEmpty()) DB::table('hr_reminders')->where('business_id', $businessId)->where('subject_type', 'training_record')->whereIn('subject_id', $trainingIds)->delete();
            if ($taskIds->isNotEmpty()) DB::table('hr_reminders')->where('business_id', $businessId)->where('subject_type', 'lifecycle_task')->whereIn('subject_id', $taskIds)->delete();
            DB::table('hr_module_events')->where('business_id', $businessId)->where('actor_id', $current->id)->delete();
            DB::table('hr_module_events')->where('business_id', $businessId)->where('subject_type', 'user')->where('subject_id', $current->id)->delete();
            if ($documentIds->isNotEmpty()) DB::table('hr_module_events')->where('business_id', $businessId)->where('subject_type', 'employee_document')->whereIn('subject_id', $documentIds)->delete();
            if ($trainingIds->isNotEmpty()) DB::table('hr_module_events')->where('business_id', $businessId)->where('subject_type', 'training_record')->whereIn('subject_id', $trainingIds)->delete();
            if ($caseIds->isNotEmpty()) DB::table('hr_module_events')->where('business_id', $businessId)->where('subject_type', 'lifecycle_case')->whereIn('subject_id', $caseIds)->delete();
            if ($taskIds->isNotEmpty()) DB::table('hr_module_events')->where('business_id', $businessId)->where('subject_type', 'lifecycle_task')->whereIn('subject_id', $taskIds)->delete();
            if ($targetedNoticeIds->isNotEmpty()) DB::table('hr_module_events')->where('business_id', $businessId)->where('subject_type', 'company_notice')->whereIn('subject_id', $targetedNoticeIds)->delete();
            DB::table('sessions')->where('user_id', $current->id)->delete();
            DB::table('password_reset_tokens')->where('email', $current->email)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $current->id)->delete();
            DB::table('users')->where('reporting_authority_id', $current->id)->update(['reporting_authority_id' => null]);
            DB::table('users')->where('leave_sanction_authority_id', $current->id)->update(['leave_sanction_authority_id' => null]);

            $this->audit($businessId, 'Employee and linked records permanently deleted', [
                'employee_id' => $current->id, 'email' => $current->email,
                'client_contact' => trim($values['client_contact']),
                'deleted_categories' => ['profile', 'attendance', 'rota', 'leave', 'documents', 'training', 'lifecycle', 'notices'],
            ]);
            $current->delete();
        });

        if ($documentPaths) Storage::disk('local')->delete($documentPaths);
        return redirect('/platform/support')->with('status', 'Employee profile and linked operational records were permanently deleted. A minimal Super Admin security audit entry was retained.');
    }

    public function sendResetLink(Request $request, int $employeeId)
    {
        $values = $request->validate([
            'client_confirmed' => 'accepted',
            'client_contact' => 'required|string|min:2|max:120',
        ]);
        $employee = User::where('role', 'employee')->findOrFail($employeeId);
        abort_unless($employee->active && DB::table('businesses')->where('id', $employee->business_id)->where('active', true)->exists(), 422, 'Activate the employee and client workspace before sending a password reset link.');

        $status = Password::sendResetLink(['email' => $employee->email]);
        $sent = $status === Password::RESET_LINK_SENT;
        $this->audit((int) $employee->business_id, $sent ? 'Employee password reset link sent' : 'Employee password reset link failed', [
            'employee_id' => $employee->id, 'email' => $employee->email, 'client_contact' => trim($values['client_contact']), 'broker_status' => $status,
        ]);

        if (!$sent) return back()->withErrors(['support' => 'The reset link could not be sent. Check the mail configuration and password reset setup.']);
        return back()->with('status', 'A secure password reset link was sent to the employee’s registered email address.');
    }

    public function updateLoginEmail(Request $request, int $employeeId)
    {
        if (is_string($request->input('email'))) $request->merge(['email' => strtolower(trim($request->input('email')))]);
        $employee = User::where('role', 'employee')->findOrFail($employeeId);
        $values = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id), Rule::unique('platform_admins', 'email')],
            'client_contact' => 'required|string|min:2|max:120',
            'client_confirmed' => 'accepted',
        ]);
        $before = $employee->email;
        if ($before === $values['email']) return back()->with('status', 'The employee’s login email is already set to that address.');

        DB::transaction(function () use ($employee, $values, $before) {
            $current = User::where('business_id', $employee->business_id)->where('role', 'employee')->where('id', $employee->id)->lockForUpdate()->firstOrFail();
            $current->update(['email' => $values['email']]);
            $current->tokens()->delete();
            DB::table('sessions')->where('user_id', $current->id)->delete();
            $this->audit((int) $current->business_id, 'Employee login email updated by support', [
                'employee_id' => $current->id, 'previous_email' => $before, 'new_email' => $current->email,
                'client_contact' => trim($values['client_contact']),
            ]);
        });
        return back()->with('status', 'Login email updated and active app sessions revoked. Ask the employee to use the new email and request a password reset if needed.');
    }

    public function employeeStatus(Request $request, int $employeeId)
    {
        $values = $request->validate([
            'active' => 'required|boolean', 'client_confirmed' => 'accepted',
            'client_contact' => 'required|string|min:2|max:120',
        ]);
        $employee = User::where('role', 'employee')->findOrFail($employeeId);
        $businessId = (int) $employee->business_id;
        DB::transaction(function () use ($employee, $businessId, $values) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $current = User::where('business_id', $businessId)->where('role', 'employee')->where('id', $employee->id)->lockForUpdate()->firstOrFail();
            $active = (bool) $values['active'];
            if ($active) {
                abort_unless(DB::table('businesses')->where('id', $businessId)->where('active', true)->exists(), 422, 'The client workspace is suspended. Reactivate it first.');
                if (!$current->active) $this->capacity->ensureCanActivateEmployee($businessId, $current->id);
            }
            $current->update(['active' => $active]);
            if (!$active) {
                $current->tokens()->delete();
                DB::table('sessions')->where('user_id', $current->id)->delete();
            }
            $this->audit($businessId, $active ? 'Employee access restored by support' : 'Employee access suspended by support', [
                'employee_id' => $current->id, 'employee_email' => $current->email, 'client_contact' => trim($values['client_contact']),
            ]);
        });
        return back()->with('status', $values['active'] ? 'Employee access restored.' : 'Employee access suspended and active app sessions revoked.');
    }

    public function correctAttendance(Request $request, int $attendanceId)
    {
        $values = $request->validate([
            'clock_in' => ['required', 'date_format:Y-m-d\\TH:i'],
            'clock_out' => ['nullable', 'date_format:Y-m-d\\TH:i', 'after:clock_in'],
            'reason' => 'required|string|min:5|max:500',
            'client_confirmed' => 'accepted',
            'client_contact' => 'required|string|min:2|max:120',
        ]);
        $candidate = DB::table('attendance_records')->where('id', $attendanceId)->firstOrFail();
        $timezone = DB::table('businesses')->where('id', $candidate->business_id)->value('timezone') ?: 'UTC';
        $in = Carbon::createFromFormat('Y-m-d\\TH:i', $values['clock_in'], $timezone)->utc();
        $out = empty($values['clock_out']) ? null : Carbon::createFromFormat('Y-m-d\\TH:i', $values['clock_out'], $timezone)->utc();

        DB::transaction(function () use ($candidate, $in, $out, $values) {
            DB::table('businesses')->where('id', $candidate->business_id)->lockForUpdate()->firstOrFail();
            User::where('business_id', $candidate->business_id)->where('id', $candidate->user_id)->lockForUpdate()->firstOrFail();
            $record = DB::table('attendance_records')->where('business_id', $candidate->business_id)->where('id', $candidate->id)->lockForUpdate()->firstOrFail();
            $this->ensureValidInterval((int) $record->user_id, $in, $out, (int) $record->id);
            $after = ['clock_in' => $in->toDateTimeString(), 'clock_out' => $out?->toDateTimeString(), 'updated_at' => now('UTC')];
            if (property_exists($record, 'finalized_at') && $record->finalized_at !== null) {
                $after['finalized_at'] = null;
                $after['finalized_by'] = null;
            }
            DB::table('attendance_records')->where('id', $record->id)->update($after);
            $afterFinalizedAt = array_key_exists('finalized_at', $after) ? $after['finalized_at'] : ($record->finalized_at ?? null);
            $this->audit((int) $record->business_id, 'Attendance corrected by platform support', [
                'employee_id' => $record->user_id, 'attendance_id' => $record->id,
                'reason' => trim($values['reason']), 'client_contact' => trim($values['client_contact']),
                'before' => ['clock_in' => $record->clock_in, 'clock_out' => $record->clock_out, 'finalized_at' => $record->finalized_at ?? null],
                'after' => ['clock_in' => $after['clock_in'], 'clock_out' => $after['clock_out'], 'finalized_at' => $afterFinalizedAt],
            ]);
        });
        return back()->with('status', 'Attendance updated. The change and client authorisation were recorded in the Super Admin audit log.');
    }

    public function addAttendance(Request $request, int $employeeId)
    {
        $values = $request->validate([
            'branch_id' => 'required|integer',
            'clock_in' => ['required', 'date_format:Y-m-d\\TH:i'],
            'clock_out' => ['nullable', 'date_format:Y-m-d\\TH:i', 'after:clock_in'],
            'reason' => 'required|string|min:5|max:500',
            'client_confirmed' => 'accepted',
            'client_contact' => 'required|string|min:2|max:120',
        ]);
        $employee = User::where('role', 'employee')->findOrFail($employeeId);
        $businessId = (int) $employee->business_id;
        $timezone = DB::table('businesses')->where('id', $businessId)->value('timezone') ?: 'UTC';
        $in = Carbon::createFromFormat('Y-m-d\\TH:i', $values['clock_in'], $timezone)->utc();
        $out = empty($values['clock_out']) ? null : Carbon::createFromFormat('Y-m-d\\TH:i', $values['clock_out'], $timezone)->utc();

        DB::transaction(function () use ($employee, $businessId, $values, $in, $out) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $current = User::where('business_id', $businessId)->where('role', 'employee')->where('id', $employee->id)->lockForUpdate()->firstOrFail();
            $branch = DB::table('branches')->where('business_id', $businessId)->where('id', $values['branch_id'])->where('active', true)->firstOrFail();
            abort_unless(DB::table('employee_branches')->where('business_id', $businessId)->where('user_id', $current->id)->where('branch_id', $branch->id)->exists(), 422, 'Choose a workplace assigned to this employee.');
            $this->ensureValidInterval((int) $current->id, $in, $out);
            $now = now('UTC');
            $id = DB::table('attendance_records')->insertGetId([
                'business_id' => $businessId, 'user_id' => $current->id, 'branch_id' => $branch->id,
                'clock_in' => $in->toDateTimeString(), 'clock_out' => $out?->toDateTimeString(),
                'in_lat' => null, 'in_lng' => null, 'out_lat' => null, 'out_lng' => null,
                'finalized_at' => null, 'finalized_by' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->audit($businessId, 'Missed attendance added by platform support', [
                'employee_id' => $current->id, 'attendance_id' => $id, 'branch_id' => $branch->id,
                'reason' => trim($values['reason']), 'client_contact' => trim($values['client_contact']), 'clock_in' => $in->toDateTimeString(), 'clock_out' => $out?->toDateTimeString(),
                'location' => 'Manual support entry; GPS coordinates were not recorded.',
            ]);
        });
        return back()->with('status', 'Missed attendance added as a manual record and included in the Super Admin audit log.');
    }

    private function ensureValidInterval(int $employeeId, Carbon $in, ?Carbon $out, ?int $excludeAttendanceId = null): void
    {
        abort_if($in->isFuture() || ($out && $out->isFuture()), 422, 'Attendance times cannot be in the future.');
        $overlap = DB::table('attendance_records')->where('user_id', $employeeId)
            ->when($excludeAttendanceId !== null, fn ($q) => $q->where('id', '!=', $excludeAttendanceId))
            ->where(fn ($q) => $q->whereNull('clock_out')->orWhere('clock_out', '>', $in->toDateTimeString()));
        if ($out) $overlap->where('clock_in', '<', $out->toDateTimeString());
        if ($overlap->exists()) {
            throw ValidationException::withMessages(['clock_in' => 'This time overlaps another attendance record. Check the employee’s other shifts first.']);
        }
    }

    private function audit(int $businessId, string $action, array $details): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_id' => Auth::guard('platform')->id(), 'business_id' => $businessId,
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now('UTC'),
        ]);
    }
}
