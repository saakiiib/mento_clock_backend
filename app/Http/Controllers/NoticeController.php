<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoticeController
{
    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $notices = DB::table('company_notices')
            ->where('business_id', $businessId)
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'published' THEN 1 ELSE 2 END")
            ->orderByDesc('publish_at')->orderByDesc('created_at')->get();
        $counts = DB::table('company_notice_receipts')->where('business_id', $businessId)->whereIn('notice_id', $notices->pluck('id'))
            ->select('notice_id', DB::raw('COUNT(*) as recipient_count'), DB::raw('SUM(CASE WHEN acknowledged_at IS NOT NULL THEN 1 ELSE 0 END) as acknowledged_count'))
            ->groupBy('notice_id')->get()->keyBy('notice_id');
        foreach ($notices as $notice) {
            $notice->recipient_count = (int) ($counts[$notice->id]->recipient_count ?? 0);
            $notice->acknowledged_count = (int) ($counts[$notice->id]->acknowledged_count ?? 0);
        }
        $branches = DB::table('branches')->where('business_id', $businessId)->where('active', true)->orderBy('name')->get();
        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)->orderBy('name')->get(['id', 'name']);
        foreach ($notices as $notice) {
            $notice->audience_label = match ($notice->audience) {
                'branch' => 'Workplace: '.($branches->firstWhere('id', $notice->target_id)->name ?? 'Unavailable'),
                'employee' => 'Employee: '.($employees->firstWhere('id', $notice->target_id)->name ?? 'Unavailable'),
                default => 'All active employees',
            };
        }
        return view('notices.index', compact('notices', 'branches', 'employees'));
    }

    public function store(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $values = $this->noticeValues($request, $businessId);
        $id = DB::table('company_notices')->insertGetId([
            'business_id' => $businessId, 'title' => $values['title'], 'body' => $values['body'],
            'audience' => $values['audience'], 'target_id' => $values['target_id'] ?? null,
            'acknowledgement_required' => (bool) ($values['acknowledgement_required'] ?? false),
            'publish_at' => $values['publish_at'] ?? null, 'expires_at' => $values['expires_at'] ?? null,
            'status' => 'draft', 'created_by' => $request->user()->id, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        $this->event($businessId, $request->user()->id, $id, 'created');
        return back()->with('status', 'Notice saved as a draft. Review it and publish when ready.');
    }

    public function updateDraft(Request $request, int $noticeId)
    {
        $businessId = (int) $request->user()->business_id;
        DB::table('company_notices')->where('business_id', $businessId)->where('id', $noticeId)->where('status', 'draft')->firstOrFail();
        $values = $this->noticeValues($request, $businessId);
        DB::table('company_notices')->where('business_id', $businessId)->where('id', $noticeId)->where('status', 'draft')->update([
            'title' => $values['title'], 'body' => $values['body'], 'audience' => $values['audience'],
            'target_id' => $values['target_id'] ?? null, 'acknowledgement_required' => (bool) ($values['acknowledgement_required'] ?? false),
            'expires_at' => $values['expires_at'] ?? null, 'updated_at' => now('UTC'),
        ]);
        $this->event($businessId, $request->user()->id, $noticeId, 'draft_updated');
        return back()->with('status', 'Draft notice updated.');
    }

    private function noticeValues(Request $request, int $businessId): array
    {
        $values = $request->validate([
            'title' => 'required|string|max:180', 'body' => 'required|string|max:20000',
            'audience' => ['required', Rule::in(['all', 'branch', 'employee'])], 'target_id' => 'nullable|integer',
            'acknowledgement_required' => 'nullable|boolean', 'expires_at' => 'nullable|date|after:today',
        ]);
        if ($values['audience'] === 'branch') {
            validator($values, ['target_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('business_id', $businessId)]])->validate();
        } elseif ($values['audience'] === 'employee') {
            validator($values, ['target_id' => ['required', 'integer', Rule::exists('users', 'id')->where('business_id', $businessId)->where('role', 'employee')]])->validate();
        } else {
            $values['target_id'] = null;
        }
        if (!empty($values['expires_at'])) {
            $timezone = DB::table('businesses')->where('id', $businessId)->value('timezone') ?: 'UTC';
            $values['expires_at'] = \Carbon\Carbon::parse($values['expires_at'], $timezone)->endOfDay()->utc()->toDateTimeString();
        }
        return $values;
    }

    public function publish(Request $request, int $noticeId)
    {
        $businessId = (int) $request->user()->business_id;
        $notice = DB::table('company_notices')->where('business_id', $businessId)->where('id', $noticeId)->firstOrFail();
        abort_if($notice->status === 'archived', 422, 'Archived notices cannot be published.');
        $now = now('UTC');
        if ($notice->publish_at && $now->lt(\Carbon\Carbon::parse($notice->publish_at, 'UTC'))) {
            return back()->withErrors(['publish_at' => 'This notice is scheduled for a future date.'])->withInput();
        }
        DB::transaction(function () use ($request, $businessId, $notice, $noticeId, $now) {
            DB::table('company_notices')->where('business_id', $businessId)->where('id', $noticeId)->update([
                'status' => 'published', 'publish_at' => $notice->publish_at ?: $now,
                'published_at' => $notice->published_at ?: $now, 'updated_at' => $now,
            ]);
            $recipients = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)->select('id');
            if ($notice->audience === 'employee') $recipients->where('id', $notice->target_id);
            if ($notice->audience === 'branch') {
                $branchUsers = DB::table('employee_branches')->where('business_id', $businessId)->where('branch_id', $notice->target_id)->select('user_id');
                $recipients->whereIn('id', $branchUsers);
            }
            $recipients->orderBy('id')->chunk(500, function ($rows) use ($businessId, $noticeId, $now) {
                $receipts = $rows->map(fn ($recipient) => [
                    'business_id' => $businessId, 'notice_id' => $noticeId, 'user_id' => $recipient->id,
                    'delivered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ])->all();
                DB::table('company_notice_receipts')->insertOrIgnore($receipts);
            });
            $this->event($businessId, $request->user()->id, $noticeId, 'published');
        });
        return back()->with('status', 'Notice published to its selected audience.');
    }

    public function archive(Request $request, int $noticeId)
    {
        $businessId = (int) $request->user()->business_id;
        DB::table('company_notices')->where('business_id', $businessId)->where('id', $noticeId)->firstOrFail();
        DB::table('company_notices')->where('business_id', $businessId)->where('id', $noticeId)->update(['status' => 'archived', 'updated_at' => now('UTC')]);
        $this->event($businessId, $request->user()->id, $noticeId, 'archived');
        return back()->with('status', 'Notice archived.');
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        $businessId = (int) $user->business_id;
        $items = DB::table('company_notices as n')->join('company_notice_receipts as r', function ($join) use ($user, $businessId) {
            $join->on('r.notice_id', '=', 'n.id')->where('r.user_id', '=', $user->id)->where('r.business_id', '=', $businessId);
        })->where('n.business_id', $businessId)->where('n.status', 'published')->where(function ($query) {
            $query->whereNull('n.publish_at')->orWhere('n.publish_at', '<=', now('UTC'));
        })->where(function ($query) {
            $query->whereNull('n.expires_at')->orWhere('n.expires_at', '>', now('UTC'));
        })->orderByDesc('n.publish_at')->limit(100)->get(['n.id', 'n.title', 'n.body', 'n.acknowledgement_required', 'n.published_at', 'n.expires_at', 'r.acknowledged_at']);
        return response()->json(['notices' => $items]);
    }

    public function acknowledge(Request $request, int $noticeId)
    {
        $user = $request->user();
        $businessId = (int) $user->business_id;
        $receipt = DB::table('company_notice_receipts as r')->join('company_notices as n', 'n.id', '=', 'r.notice_id')
            ->where('r.business_id', $businessId)->where('r.user_id', $user->id)->where('r.notice_id', $noticeId)
            ->where('n.business_id', $businessId)->where('n.status', 'published')->where('n.acknowledgement_required', true)
            ->where(function ($query) { $query->whereNull('n.expires_at')->orWhere('n.expires_at', '>', now('UTC')); })
            ->first(['r.id', 'r.acknowledged_at']);
        abort_unless($receipt, 404);
        DB::table('company_notice_receipts')->where('id', $receipt->id)->update(['acknowledged_at' => $receipt->acknowledged_at ?: now('UTC'), 'updated_at' => now('UTC')]);
        $this->event($businessId, $user->id, $noticeId, 'acknowledged');
        return response()->json(['message' => 'Notice acknowledged.']);
    }

    private function event(int $businessId, int $actorId, int $noticeId, string $action): void
    {
        DB::table('hr_module_events')->insert(['business_id' => $businessId, 'actor_id' => $actorId, 'subject_type' => 'company_notice', 'subject_id' => $noticeId, 'action' => $action, 'created_at' => now('UTC')]);
    }
}
