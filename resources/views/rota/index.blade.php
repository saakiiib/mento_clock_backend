@extends('layout')
@section('title', 'Rota & shifts')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Workforce planning</span><h1>Rota & shifts</h1><p>Build this week’s schedule and check it against leave requests and company closures before publishing.</p></div></div>

<section class="card">
    <div class="card-head"><div><h2>Week starting {{ $start->format('d M Y') }}</h2><p>Times shown in {{ $timezone }}. Draft shifts are visible to managers only.</p></div><div class="actions"><a class="button secondary" href="{{url('/rota?week='.$start->subWeek()->toDateString())}}">← Previous week</a><a class="button secondary" href="{{url('/rota?week='.$start->addWeek()->toDateString())}}">Next week →</a></div></div>
    <div class="scroll"><table><thead><tr><th>Day / time</th><th>Employee</th><th>Shift</th><th>Workplace</th><th>Break</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($shifts as $shift)
        @php $shiftStart = Carbon\Carbon::parse($shift->starts_at, 'UTC')->setTimezone($timezone); $shiftEnd = Carbon\Carbon::parse($shift->ends_at, 'UTC')->setTimezone($timezone); @endphp
        <tr><td><strong>{{$shiftStart->format('D, d M')}}</strong><small><br>{{$shiftStart->format('H:i')}}–{{$shiftEnd->format('H:i')}}</small></td><td><strong>{{$shift->employee_name}}</strong><small><br>{{$shift->employee_code ?: ''}}</small></td><td>{{$shift->title}}</td><td>{{$shift->branch_name ?: '—'}}</td><td>{{$shift->break_minutes}} min</td><td><span class="badge {{$shift->status === 'cancelled' ? 'inactive' : ($shift->status === 'draft' ? 'neutral' : '')}}">{{ucfirst($shift->status)}}</span></td><td><div class="actions">@if($shift->status === 'draft')<details><summary>Edit draft</summary><form method="post" action="{{url('/rota/shifts/'.$shift->id)}}" class="form-two">@csrf
            <label>Employee<select name="user_id" required>@foreach($employees as $employee)<option value="{{$employee->id}}" @selected((int)$employee->id === (int)$shift->user_id)>{{$employee->name}}</option>@endforeach</select></label>
            <label>Workplace<select name="branch_id"><option value="">No workplace</option>@foreach($branches as $branch)<option value="{{$branch->id}}" @selected((int)$branch->id === (int)$shift->branch_id)>{{$branch->name}}</option>@endforeach</select></label>
            <label>Shift title<input name="title" maxlength="120" value="{{$shift->title}}" required></label>
            <label>Unpaid break (minutes)<input name="break_minutes" type="number" min="0" max="600" value="{{$shift->break_minutes}}" required></label>
            <label>Starts<input name="starts_at" type="datetime-local" value="{{$shiftStart->format('Y-m-d\\TH:i')}}" required></label>
            <label>Ends<input name="ends_at" type="datetime-local" value="{{$shiftEnd->format('Y-m-d\\TH:i')}}" required></label>
            <label>Notes<input name="notes" maxlength="2000" value="{{$shift->notes}}"></label><button>Save draft</button></form></details><form method="post" action="{{url('/rota/shifts/'.$shift->id.'/publish')}}">@csrf<button>Publish</button></form>@endif @if($shift->status !== 'cancelled')<form method="post" action="{{url('/rota/shifts/'.$shift->id.'/cancel')}}" data-confirm="Cancel this shift? The change will be recorded.">@csrf<button class="danger">Cancel</button></form>@endif</div></td></tr>
    @empty<tr><td colspan="7"><div class="empty-state"><h3>No shifts for this week.</h3><p>Add a draft shift below to start planning.</p></div></td></tr>@endforelse
    </tbody></table></div>
</section>

@if(app(\App\Services\ModuleAccess::class)->enabled((int)auth()->user()->business_id,'rota_advanced'))
<section class="card"><div class="card-head"><div><span class="eyebrow">REUSABLE ROTAS</span><h2>Templates</h2><p>Save a planned week as a reusable pattern. Applying a template creates drafts and rechecks leave, availability and shift conflicts.</p></div></div><div class="form-two"><form method="post" action="{{url('/rota/templates')}}">@csrf<input type="hidden" name="week" value="{{$start->toDateString()}}"><label for="template_name">Save this week as a template</label><input id="template_name" name="name" maxlength="120" placeholder="e.g. Standard 5-day week" required><button class="secondary">Save template</button></form><form method="post" action="{{url('/rota/templates/apply')}}">@csrf<label for="template_id">Apply a template to a week</label><select id="template_id" name="template_id" required><option value="">Choose template</option>@foreach($templates as $template)<option value="{{$template->id}}">{{$template->name}}</option>@endforeach</select><label for="template_week">Week starting</label><input id="template_week" type="date" name="week" value="{{$start->toDateString()}}" required><button class="secondary">Add as draft shifts</button></form></div></section>

<section class="card"><div class="card-head"><div><span class="eyebrow">AVAILABILITY</span><h2>Employee availability</h2><p>Record unavailable dates or times; new shifts that overlap an unavailable period will be blocked.</p></div></div><form method="post" action="{{url('/rota/availability')}}" class="form-two">@csrf<div><label for="availability_employee">Employee</label><select name="user_id" id="availability_employee" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{$employee->id}}">{{$employee->name}}</option>@endforeach</select></div><div><label for="availability_kind">Availability</label><select name="availability" id="availability_kind"><option value="unavailable">Unavailable</option><option value="available">Available note</option></select></div><div><label for="availability_start">From</label><input type="datetime-local" name="starts_at" id="availability_start" value="{{$start->format('Y-m-d')}}T09:00" required></div><div><label for="availability_end">Until</label><input type="datetime-local" name="ends_at" id="availability_end" value="{{$start->format('Y-m-d')}}T17:00" required></div><div><label for="availability_notes">Note (optional)</label><input name="notes" id="availability_notes" maxlength="500"></div><div class="form-actions"><button class="secondary">Save availability</button></div></form>
<div class="scroll"><table><thead><tr><th>Employee</th><th>Period</th><th>Availability</th><th>Note</th><th></th></tr></thead><tbody>@forelse($availability as $item)<tr><td>{{$item->employee_name}}</td><td>{{Carbon\Carbon::parse($item->starts_at,'UTC')->setTimezone($timezone)->format('d M H:i')}} – {{Carbon\Carbon::parse($item->ends_at,'UTC')->setTimezone($timezone)->format('d M H:i')}}</td><td>{{ucfirst($item->availability)}}</td><td>{{$item->notes}}</td><td><form method="post" action="{{url('/rota/availability/'.$item->id.'/delete')}}" data-confirm="Remove this availability period?">@csrf<button class="danger">Remove</button></form></td></tr>@empty<tr><td colspan="5"><p class="caption">No availability records for this week.</p></td></tr>@endforelse</tbody></table></div></section>
@endif

<section class="card">
    <div class="card-head"><div><h2>Add a shift</h2><p>Shifts are saved as drafts first. The system blocks overlaps with pending or approved leave, business closures and the employee’s other shifts.</p></div></div>
    <form method="post" action="{{url('/rota/shifts')}}">@csrf
        <div class="form-two">
            <div><label for="user_id">Employee</label><select id="user_id" name="user_id" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{$employee->id}}">{{$employee->name}}{{$employee->employee_code ? ' · '.$employee->employee_code : ''}}</option>@endforeach</select></div>
            <div><label for="branch_id">Workplace</label><select id="branch_id" name="branch_id"><option value="">No workplace</option>@foreach($branches as $branch)<option value="{{$branch->id}}">{{$branch->name}}</option>@endforeach</select><small>Only choose a workplace assigned to the employee.</small></div>
            <div><label for="title">Shift title</label><input id="title" name="title" maxlength="120" value="Shift" required></div>
            <div><label for="break_minutes">Unpaid break (minutes)</label><input id="break_minutes" name="break_minutes" type="number" min="0" max="600" value="0" required></div>
            <div><label for="starts_at">Starts</label><input id="starts_at" name="starts_at" type="datetime-local" value="{{$start->format('Y-m-d')}}T09:00" required></div>
            <div><label for="ends_at">Ends</label><input id="ends_at" name="ends_at" type="datetime-local" value="{{$start->format('Y-m-d')}}T17:30" required></div>
            <div><label for="notes">Notes (optional)</label><input id="notes" name="notes" maxlength="2000"></div>
        </div>
        <div class="form-actions"><button>Save draft shift</button></div>
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
