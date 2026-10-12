<?php

namespace App\Http\Controllers;

use App\Services\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReminderController
{
    public function __construct(private WorkspacePermissions $permissions) {}

    public function index(Request $request)
    {
        $user = $request->user(); abort_unless($user?->active && $user->business_id, 403);
        $this->permissions->require($user, 'reminders.view');
        $reminders = DB::table('hr_reminders')->where('business_id', $user->business_id)
            ->orderByRaw('read_at IS NULL DESC')->orderByDesc('remind_on')->paginate(50);
        return view('reminders.index', compact('reminders'));
    }

    public function markRead(Request $request, int $reminderId)
    {
        $user = $request->user(); abort_unless($user?->active && $user->business_id, 403);
        $this->permissions->require($user, 'reminders.view');
        $updated = DB::table('hr_reminders')->where('business_id', $user->business_id)->where('id', $reminderId)
            ->update(['read_at' => now('UTC'), 'updated_at' => now('UTC')]);
        abort_unless($updated, 404);
        return back()->with('status', 'Reminder marked as reviewed.');
    }
}
