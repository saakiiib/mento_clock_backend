@extends('layout')
@section('title', 'Rota & shifts')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Workforce planning</span><h1>Rota & shifts</h1><p>Build this week’s schedule and check it against leave requests and company closures before publishing.</p></div>
    <div class="rota-export-actions">
        <span class="caption">Download a print-ready PDF.</span>
        <form method="get" action="{{url('/rota/export')}}" class="rota-week-export"><input type="hidden" name="period" value="week"><input type="hidden" name="date" value="{{$start->toDateString()}}"><label class="rota-break-toggle"><input type="checkbox" name="show_breaks" value="1"> Include break times</label><button class="secondary">Download week PDF</button></form>
        <form method="get" action="{{url('/rota/export')}}" class="rota-month-export"><input type="hidden" name="period" value="month"><label for="rota_export_month">Month<input type="month" id="rota_export_month" name="month" value="{{$start->format('Y-m')}}" required></label><label class="rota-break-toggle"><input type="checkbox" name="show_breaks" value="1"> Include break times</label><button class="secondary">Download month PDF</button></form>
    </div>
</div>

<section class="card">
    <div class="card-head"><div><h2>Week starting {{ $start->format('d M Y') }}</h2><p>Times shown in {{ $timezone }}. Draft shifts are visible to managers only.</p></div><div class="actions"><a class="button secondary" href="{{url('/rota?week='.$start->subWeek()->toDateString())}}">← Previous week</a><a class="button secondary" href="{{url('/rota?week='.$start->addWeek()->toDateString())}}">Next week →</a>@if($draftCount)<form method="post" action="{{url('/rota/publish-week')}}" data-confirm="Publish all {{$draftCount}} draft shifts for this week? Employees will be able to see them in the app.">@csrf<input type="hidden" name="week" value="{{$start->toDateString()}}"><button>Publish all {{$draftCount}} drafts</button></form>@endif</div></div>
    <p class="rota-grid-hint">Each row is an employee and each column is a day. Add several shifts below or reuse a weekly template.</p>
    <div class="scroll rota-grid-scroll"><table class="rota-grid"><thead><tr><th>Employee</th>@foreach($weekDays as $day)<th class="rota-day-heading"><strong>{{$day->format('D')}}</strong><span>{{$day->format('d M')}}</span>@foreach($holidays->where('holiday_date',$day->toDateString()) as $holiday)<small class="rota-day-closure">{{$holiday->is_closure ? 'Closed · ' : ''}}{{$holiday->name}}</small>@endforeach</th>@endforeach</tr></thead><tbody>
    @forelse($employees as $employee)
        <tr><th class="rota-employee-cell"><strong>{{$employee->name}}</strong><small>{{$employee->employee_code ?: 'Employee'}}</small></th>
        @foreach($weekDays as $day)
            <td class="rota-day-cell">
                @forelse($shiftGrid[$employee->id][$day->toDateString()] ?? [] as $shift)
                    <article class="rota-shift-card {{$shift->status === 'cancelled' ? 'is-cancelled' : ''}}">
                        <div class="rota-shift-card-head"><strong>{{$shift->title}}</strong><span class="badge {{$shift->status === 'cancelled' ? 'inactive' : ($shift->status === 'draft' ? 'neutral' : 'success')}}">{{ucfirst($shift->status)}}</span></div>
                        <span class="rota-shift-time">{{$shift->local_start->format('H:i')}}–{{$shift->local_end->format('H:i')}}@unless($shift->local_start->isSameDay($shift->local_end)) +1 day @endunless</span>
                        <small>{{$shift->branch_name ?: 'No workplace'}} · Break {{$shift->break_minutes}} min</small>
                        @if($shift->status === 'draft')<details class="rota-shift-edit"><summary>Edit</summary><form method="post" action="{{url('/rota/shifts/'.$shift->id)}}">@csrf
                            <label>Employee<select name="user_id" required>@foreach($employees as $option)<option value="{{$option->id}}" @selected((int)$option->id === (int)$shift->user_id)>{{$option->name}}</option>@endforeach</select></label>
                            <label>Workplace<select name="branch_id"><option value="">No workplace</option>@foreach($branches as $branch)<option value="{{$branch->id}}" @selected((int)$branch->id === (int)$shift->branch_id)>{{$branch->name}}</option>@endforeach</select></label>
                            <label>Shift title<input name="title" maxlength="120" value="{{$shift->title}}" required></label>
                            <label>Break minutes<input name="break_minutes" type="number" min="0" max="600" value="{{$shift->break_minutes}}" required></label>
                            <label>Starts<input name="starts_at" type="datetime-local" value="{{$shift->local_start->format('Y-m-d\\TH:i')}}" required></label>
                            <label>Ends<input name="ends_at" type="datetime-local" value="{{$shift->local_end->format('Y-m-d\\TH:i')}}" required></label>
                            <label>Notes<input name="notes" maxlength="2000" value="{{$shift->notes}}"></label><button class="secondary">Save draft</button></form></details>
                            <div class="rota-shift-actions"><form method="post" action="{{url('/rota/shifts/'.$shift->id.'/publish')}}">@csrf<button>Publish</button></form><form method="post" action="{{url('/rota/shifts/'.$shift->id.'/cancel')}}" data-confirm="Cancel this shift? The change will be recorded.">@csrf<button class="danger">Cancel</button></form></div>
                        @elseif($shift->status === 'published')<form method="post" action="{{url('/rota/shifts/'.$shift->id.'/cancel')}}" data-confirm="Cancel this published shift? The employee’s rota will be updated and the change recorded.">@csrf<button class="danger">Cancel shift</button></form>@endif
                    </article>
                @empty<span class="rota-empty-cell">—</span>@endforelse
            </td>
        @endforeach
        </tr>
    @empty<tr><td colspan="8"><div class="empty-state"><h3>No active employees yet.</h3><p>Add employees before planning a rota.</p></div></td></tr>@endforelse
    </tbody></table></div>
</section>

@if(app(\App\Services\ModuleAccess::class)->enabled((int)auth()->user()->business_id,'rota_advanced'))
<section class="card"><div class="card-head"><div><span class="eyebrow">REUSABLE ROTAS</span><h2>Templates</h2><p>A template copies shift times, weekdays, employees and workplaces into another week.</p></div></div>
    <div class="rota-template-guide"><strong>How to use a template</strong><ol><li>Open the week you want to reuse, add or edit its shifts, then enter a name under “Save this week as a template”.</li><li>Choose that saved template and select a date in the target week.</li><li>Select “Add as draft shifts”, review the new shifts and resolve any conflicts, then publish them when ready.</li></ol><p>Templates add draft shifts only. Leave, availability, workplace assignments and overlapping shifts are checked again when you apply one.</p></div>
    @if($templates->isEmpty())<p class="note">No templates yet. Add at least one shift to a week, then save that week as a template.</p>@endif
    <div class="form-two"><form method="post" action="{{url('/rota/templates')}}">@csrf<input type="hidden" name="week" value="{{$start->toDateString()}}"><label for="template_name">Save the displayed week as a template</label><input id="template_name" name="name" maxlength="120" placeholder="e.g. Standard 5-day week" required><button class="secondary">Save this week as template</button></form><form method="post" action="{{url('/rota/templates/apply')}}">@csrf<label for="template_id">Choose a saved template</label><select id="template_id" name="template_id" required><option value="">Choose template</option>@foreach($templates as $template)<option value="{{$template->id}}">{{$template->name}}</option>@endforeach</select><label for="template_week">Date in the week to fill</label><input id="template_week" type="date" name="week" value="{{$start->toDateString()}}" required><button class="secondary">Add as draft shifts</button></form></div>
</section>

<section class="card"><div class="card-head"><div><span class="eyebrow">AVAILABILITY</span><h2>Employee availability</h2><p>Record unavailable dates or times; new shifts that overlap an unavailable period will be blocked.</p></div></div><form method="post" action="{{url('/rota/availability')}}" class="form-two">@csrf<div><label for="availability_employee">Employee</label><select name="user_id" id="availability_employee" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{$employee->id}}">{{$employee->name}}</option>@endforeach</select></div><div><label for="availability_kind">Availability</label><select name="availability" id="availability_kind"><option value="unavailable">Unavailable</option><option value="available">Available note</option></select></div><div><label for="availability_start">From</label><input type="datetime-local" name="starts_at" id="availability_start" value="{{$start->format('Y-m-d')}}T09:00" required></div><div><label for="availability_end">Until</label><input type="datetime-local" name="ends_at" id="availability_end" value="{{$start->format('Y-m-d')}}T17:00" required></div><div><label for="availability_notes">Note (optional)</label><input name="notes" id="availability_notes" maxlength="500"></div><div class="form-actions"><button class="secondary">Save availability</button></div></form>
<div class="scroll"><table><thead><tr><th>Employee</th><th>Period</th><th>Availability</th><th>Note</th><th></th></tr></thead><tbody>@forelse($availability as $item)<tr><td>{{$item->employee_name}}</td><td>{{Carbon\Carbon::parse($item->starts_at,'UTC')->setTimezone($timezone)->format('d M H:i')}} – {{Carbon\Carbon::parse($item->ends_at,'UTC')->setTimezone($timezone)->format('d M H:i')}}</td><td>{{ucfirst($item->availability)}}</td><td>{{$item->notes}}</td><td><form method="post" action="{{url('/rota/availability/'.$item->id.'/delete')}}" data-confirm="Remove this availability period?">@csrf<button class="danger">Remove</button></form></td></tr>@empty<tr><td colspan="5"><p class="caption">No availability records for this week.</p></td></tr>@endforelse</tbody></table></div></section>
@endif

<section class="card">
    <div class="card-head"><div><h2>Add shifts to this rota</h2><p>Add several employee shifts in one go. Every shift is checked for leave, closures, availability and overlaps before anything is saved.</p></div></div>
    <form method="post" action="{{url('/rota/shifts/batch')}}" data-rota-batch>@csrf
        <div class="rota-batch-list" data-rota-batch-list>
            <div class="rota-batch-row" data-rota-row>
                <label>Employee<select name="shifts[0][user_id]" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{$employee->id}}">{{$employee->name}}{{$employee->employee_code ? ' · '.$employee->employee_code : ''}}</option>@endforeach</select></label>
                <label>Workplace<select name="shifts[0][branch_id]"><option value="">No workplace</option>@foreach($branches as $branch)<option value="{{$branch->id}}">{{$branch->name}}</option>@endforeach</select></label>
                <label>Shift title<input name="shifts[0][title]" maxlength="120" value="Shift" required></label>
                <label>Starts<input name="shifts[0][starts_at]" type="datetime-local" value="{{$start->format('Y-m-d')}}T09:00" required></label>
                <label>Ends<input name="shifts[0][ends_at]" type="datetime-local" value="{{$start->format('Y-m-d')}}T17:30" required></label>
                <label>Break (minutes)<input name="shifts[0][break_minutes]" type="number" min="0" max="600" value="0" required></label>
                <label>Note (optional)<input name="shifts[0][notes]" maxlength="2000"></label>
                <button type="button" class="danger" data-remove-rota-row>Remove row</button>
            </div>
        </div>
        <template data-rota-row-template><div class="rota-batch-row" data-rota-row>
            <label>Employee<select name="shifts[__INDEX__][user_id]" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{$employee->id}}">{{$employee->name}}{{$employee->employee_code ? ' · '.$employee->employee_code : ''}}</option>@endforeach</select></label>
            <label>Workplace<select name="shifts[__INDEX__][branch_id]"><option value="">No workplace</option>@foreach($branches as $branch)<option value="{{$branch->id}}">{{$branch->name}}</option>@endforeach</select></label>
            <label>Shift title<input name="shifts[__INDEX__][title]" maxlength="120" value="Shift" required></label>
            <label>Starts<input name="shifts[__INDEX__][starts_at]" type="datetime-local" value="{{$start->format('Y-m-d')}}T09:00" required></label>
            <label>Ends<input name="shifts[__INDEX__][ends_at]" type="datetime-local" value="{{$start->format('Y-m-d')}}T17:30" required></label>
            <label>Break (minutes)<input name="shifts[__INDEX__][break_minutes]" type="number" min="0" max="600" value="0" required></label>
            <label>Note (optional)<input name="shifts[__INDEX__][notes]" maxlength="2000"></label>
            <button type="button" class="danger" data-remove-rota-row>Remove row</button>
        </div></template>
        <div class="form-actions"><button type="button" class="secondary" data-add-rota-row>Add another shift</button><button>Save all as drafts</button></div>
    </form>
</section>

<section class="card">
    <div class="card-head"><div><h2>Leave and closure checks</h2><p>Resolve pending requests and rota conflicts before publishing the employee schedule.</p></div></div>
    <div class="form-two">
        <div><h3>Leave requests this week</h3>@forelse($leave as $item)<div class="activity-item"><strong>{{$item->employee_name}} · {{ucfirst($item->status)}} leave</strong><span>{{Carbon\Carbon::parse($item->start_date)->format('d M')}}–{{Carbon\Carbon::parse($item->end_date)->format('d M Y')}} · {{ucfirst($item->leave_type)}}</span></div>@empty<p class="caption">No pending or approved leave this week.</p>@endforelse</div>
        <div><h3>Calendar entries this week</h3>@forelse($holidays as $holiday)<div class="activity-item"><strong>{{$holiday->name}}{{$holiday->is_closure ? ' · business closed' : ''}}</strong><span>{{Carbon\Carbon::parse($holiday->holiday_date)->format('D, d M Y')}} · {{str_replace('_',' ',ucfirst($holiday->region))}}</span></div>@empty<p class="caption">No holiday or closure entries this week.</p>@endforelse</div>
    </div>
    <div class="note">Approving leave that overlaps a draft or published shift is blocked. Cancel or move the shift first. Cancelling a shift keeps its audit history. Public holidays do not automatically close the business; mark company closure dates in Holiday Calendar to prevent rota shifts on those dates.</div>
</section>
@endsection
