@extends('layout')
@section('title', $tab === 'calendar' ? 'Holiday calendar' : 'Leave management')
@section('content')
<div class="page-heading"><div><span class="eyebrow">People</span><h1>{{$tab === 'calendar' ? 'Holiday calendar' : 'Leave management'}}</h1><p>{{$tab === 'calendar' ? 'Keep your company closures and regional holidays in one place.' : 'Set leave rules, review requests and keep employee balances visible.'}}</p></div><div class="actions">@if(app(\App\Services\ModuleAccess::class)->enabled((int)auth()->user()->business_id,'leave_management'))<a class="button secondary" href="{{url('/leave')}}">Leave management</a>@endif @if(app(\App\Services\ModuleAccess::class)->enabled((int)auth()->user()->business_id,'holiday_calendar'))<a class="button secondary" href="{{url('/calendar')}}">Holiday calendar</a>@endif</div></div>

@if($tab === 'leave')
<div class="stats">
    <article class="card stat"><div class="stat-head">PENDING REQUESTS</div><div class="number">{{$requests->where('status','pending')->count()}}</div><small>Waiting for review</small></article>
    <article class="card stat"><div class="stat-head">ACTIVE EMPLOYEES</div><div class="number">{{$employees->count()}}</div><small>With leave profiles</small></article>
    <article class="card stat"><div class="stat-head">APPROVED UPCOMING</div><div class="number">{{$requests->where('status','approved')->where('start_date','>=',today()->toDateString())->count()}}</div><small>Approved requests in the list</small></article>
    <article class="card stat"><div class="stat-head">CALENDAR ENTRIES</div><div class="number">{{$holidays->count()}}</div><small>Next 12 months</small></article>
</div>

<section class="card">
    <div class="card-head"><div><h2>Leave policy</h2><p>Set the leave year, default annual allowance and public-holiday region for this workspace.</p></div></div>
    <form method="post" action="{{url('/leave/policy')}}">@csrf
        <div class="form-two">
            <div><label for="leave_year_start_month">Leave year starts in</label><select id="leave_year_start_month" name="leave_year_start_month" required>@foreach(range(1,12) as $month)<option value="{{$month}}" @selected((int)$policy->leave_year_start_month === $month)>{{Carbon\Carbon::create()->month($month)->format('F')}}</option>@endforeach</select></div>
            <div><label for="leave_year_start_day">Day of month</label><input id="leave_year_start_day" type="number" name="leave_year_start_day" min="1" max="31" value="{{$policy->leave_year_start_day}}" required></div>
            <div><label for="default_annual_entitlement">Default annual entitlement (working days)</label><input id="default_annual_entitlement" type="number" name="default_annual_entitlement" min="0" max="366" step="1" value="{{$policy->default_annual_entitlement}}" required><small>Use the entitlement agreed in each employee’s contract. No statutory entitlement is assumed by the system.</small></div>
            <div><label for="holiday_region">Public-holiday region</label><select id="holiday_region" name="holiday_region" required>@foreach(['england_wales'=>'England and Wales','scotland'=>'Scotland','northern_ireland'=>'Northern Ireland','custom'=>'Custom calendar'] as $value=>$label)<option value="{{$value}}" @selected($policy->holiday_region === $value)>{{$label}}</option>@endforeach</select></div>
        </div>
        <div class="form-actions"><button>Save leave policy</button></div>
    </form>
    <div class="note">Balances use approved annual-leave requests and the configured working days and business holidays. Carry-over, accrual, part-year proration and statutory calculations are not included in this release. Request day counts are recorded when submitted; review them if a work pattern or calendar entry changes afterwards.</div>
</section>

<section class="card">
    <div class="card-head"><div><h2>Employee allowances and working days</h2><p>Leave a personal allowance blank to use the workspace default. Select the days each employee normally works.</p></div></div>
    <div class="scroll"><table><thead><tr><th>Employee</th><th>Annual allowance</th><th>Working days</th><th>Balance</th><th></th></tr></thead><tbody>
    @forelse($employees as $employee)
        @php $selectedDays = $employee->working_days ? json_decode($employee->working_days, true) : ['mon','tue','wed','thu','fri']; @endphp
        <tr><td><strong>{{$employee->name}}</strong><small><br>{{$employee->employee_code ?: 'No employee code'}}</small></td><td colspan="4">
            <form method="post" action="{{url('/leave/employees/'.$employee->id)}}">@csrf
                <div class="form-two" style="align-items:end"><div><label>Personal override (days; blank uses default)</label><input type="number" name="annual_entitlement_override" min="0" max="366" step="1" value="{{$employee->annual_entitlement_override}}" placeholder="Use workspace default"></div><div><label>Working weekdays</label><div class="checks">@foreach(['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'] as $day=>$label)<label><input type="checkbox" name="working_days[]" value="{{$day}}" @checked(in_array($day,$selectedDays,true))> {{$label}}</label>@endforeach</div></div></div>
                <div class="actions" style="margin-top:12px"><span class="caption">Balance: {{number_format($balances[$employee->id]['remaining'],1)}} days left · {{number_format($balances[$employee->id]['used'],1)}} approved</span><button class="secondary">Save employee leave setup</button></div>
            </form>
        </td></tr>
    @empty<tr><td colspan="5"><div class="empty-state"><h3>No active employees yet.</h3><p>Add an employee before configuring a leave allowance.</p></div></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="card">
    <div class="card-head"><div><h2>Leave requests</h2><p>Requests are scoped to this workspace. Every review decision is recorded.</p></div></div>
    <div class="scroll"><table><thead><tr><th>Employee</th><th>Type and dates</th><th>Days</th><th>Status</th><th>Reason / review note</th><th>Action</th></tr></thead><tbody>
    @forelse($requests as $item)
        <tr><td><strong>{{$item->employee_name}}</strong><small><br>{{$item->employee_code ?: ''}}</small></td><td>{{ucfirst($item->leave_type)}}<small><br>{{Carbon\Carbon::parse($item->start_date)->format('d M Y')}} – {{Carbon\Carbon::parse($item->end_date)->format('d M Y')}}</small></td><td>{{number_format((float)$item->working_days,1)}}</td><td><span class="badge {{$item->status === 'pending' ? 'neutral' : ($item->status === 'rejected' || $item->status === 'cancelled' ? 'inactive' : '')}}">{{ucfirst($item->status)}}</span>@if($item->reviewer_name)<small><br>by {{$item->reviewer_name}}</small>@endif</td><td style="white-space:normal;max-width:240px">{{$item->reason ?: '—'}}@if($item->review_note)<small><br>{{$item->review_note}}</small>@endif</td><td>@if($item->status === 'pending')<form method="post" action="{{url('/leave/requests/'.$item->id.'/review')}}">@csrf<label>Optional note</label><input name="review_note" maxlength="2000"><div class="actions" style="margin-top:8px"><button name="decision" value="approved">Approve</button><button class="danger" name="decision" value="rejected">Decline</button></div></form>@else — @endif</td></tr>
    @empty<tr><td colspan="6"><div class="empty-state"><h3>No leave requests yet.</h3><p>Employee requests submitted from the existing app will appear here.</p></div></td></tr>@endforelse
    </tbody></table></div>
</section>
@else
<section class="card">
    <div class="card-head"><div><h2>Add a holiday or business closure</h2><p>Entries are maintained by your workspace administrator and excluded from annual-leave day counts when their region applies. Only entries marked as a business closure block rota shifts.</p></div></div>
    <form method="post" action="{{url('/calendar/holidays')}}">@csrf
        <div class="form-two"><div><label for="holiday_name">Name</label><input id="holiday_name" name="name" maxlength="140" required placeholder="e.g. Christmas Day"></div><div><label for="holiday_date">Date</label><input id="holiday_date" name="holiday_date" type="date" required></div><div><label for="holiday_region_entry">Applies to</label><select id="holiday_region_entry" name="region" required>@foreach(['all'=>'All regions','england_wales'=>'England and Wales','scotland'=>'Scotland','northern_ireland'=>'Northern Ireland','local'=>'Local / company closure'] as $value=>$label)<option value="{{$value}}">{{$label}}</option>@endforeach</select></div><div><label for="holiday_notes">Notes (optional)</label><input id="holiday_notes" name="notes" maxlength="1000"></div><div class="checks"><label><input type="checkbox" name="is_closure" value="1"> Business closed — prevent rota shifts on this date</label></div></div>
        <div class="form-actions"><button>Add to calendar</button></div>
    </form>
</section>
<section class="card">
    <div class="card-head"><div><h2>Upcoming calendar · next 12 months</h2><p>Calendar dates are maintained manually in this release; please verify local substitute days when entering public holidays.</p></div></div>
    <div class="scroll"><table><thead><tr><th>Date</th><th>Holiday / closure</th><th>Region</th><th>Rota</th><th>Notes</th><th></th></tr></thead><tbody>
    @forelse($holidays as $holiday)<tr><td>{{Carbon\Carbon::parse($holiday->holiday_date)->format('D, d M Y')}}</td><td><strong>{{$holiday->name}}</strong></td><td>{{str_replace('_',' ',ucfirst($holiday->region))}}</td><td>{{$holiday->is_closure ? 'Closed' : 'Scheduling allowed'}}</td><td>{{$holiday->notes ?: '—'}}</td><td><form method="post" action="{{url('/calendar/holidays/'.$holiday->id.'/delete')}}" data-confirm="Remove this calendar entry?">@csrf<button class="danger">Remove</button></form></td></tr>
    @empty<tr><td colspan="6"><div class="empty-state"><h3>Your calendar is ready.</h3><p>Add the holidays and local closures that apply to your business.</p></div></td></tr>@endforelse
    </tbody></table></div>
</section>
@endif
<section class="card">
    <div class="card-head"><div><h2>Workspace change history</h2><p>Policy, employee leave setup and holiday calendar changes.</p></div></div>
    @forelse($adminEvents as $event)<div class="activity-item"><strong>{{str_replace('_',' ',ucfirst($event->action))}}</strong><span>{{$event->subject_type}}{{ $event->subject_id ? ' #'.$event->subject_id : '' }} · {{$event->actor_name ?: 'Former administrator'}} · {{Carbon\Carbon::parse($event->created_at)->format('d M Y, H:i')}}</span></div>@empty<p class="caption">Calendar and policy changes will be recorded here.</p>@endforelse
</section>
@endsection
