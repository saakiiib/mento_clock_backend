@extends('layout')
@section('title','Notifications')
@section('content')
<div class="page-head"><div><span class="eyebrow">MentoClock V39</span><h1>Notifications &amp; announcements</h1><p>Send targeted messages to employees. Messages appear in the mobile app inbox.</p></div></div>
@if(session('status'))<div class="card" role="status" style="padding:14px;margin-bottom:16px">{{session('status')}}</div>@endif
@if($errors->any())<div class="card" role="alert" style="padding:14px;margin-bottom:16px;color:#b91c1c">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
<section class="card" style="padding:24px;margin-bottom:24px"><h2>Create announcement</h2>
<form method="post" action="{{url($platform?'/platform/notifications':'/notifications')}}">@csrf
<div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
<label>Template<select id="v39-template" onchange="v39Template(this.value)"><option value="custom">Custom</option><option value="profile">Update contact information</option><option value="feedback">Request feedback</option><option value="review">App store review</option><option value="update">Update the app</option><option value="attendance">Attendance reminder</option></select></label>
<label>Action<select name="action_type" id="v39-action" required><option value="none">Information only</option><option value="profile">Open profile</option><option value="feedback">Feedback</option><option value="review">Honest app review</option><option value="update">Update app</option><option value="attendance">Attendance information</option></select></label>
<label>Recipients<select name="target_type" id="v39-target" required><option value="business">{{ $platform ? 'Selected business' : 'All employees in this business' }}</option>@if($platform)<option value="all">All businesses</option>@endif<option value="branch">Specific workplace</option><option value="employee">One employee</option></select></label>
@if($platform)<label>Business<select name="business_id" id="v39-business" onchange="v39LoadTargets()"><option value="">Select business (not needed for all)</option>@foreach($businesses as $b)<option value="{{$b->id}}">{{$b->name}}</option>@endforeach</select></label>@endif
<label>Workplace (specific workplace only)<select name="branch_id" id="v39-branch"><option value="">Select workplace</option>@foreach($branches as $b)<option value="{{$b->id}}">{{$b->name}}</option>@endforeach</select></label>
<label>Employee (one employee only)<select name="employee_id" id="v39-employee"><option value="">Select employee</option>@foreach($employees as $e)<option value="{{$e->id}}">{{$e->name}}</option>@endforeach</select></label>
<label>Scheduled UTC delivery (optional)<input type="datetime-local" name="scheduled_at" value=""></label>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="requires_ack" value="1"> Requires employee acknowledgement</label>
</div>
<label>Title<input name="title" id="v39-title" maxlength="160" required value="{{old('title')}}"></label>
<label>Message<textarea name="body" id="v39-body" maxlength="2000" rows="4" required>{{old('body')}}</textarea></label>
<p class="muted">Messages are sent to active employees. No client passwords are required. Scheduled messages need the Laravel scheduler. Push delivery is separate from the in-app inbox.</p>
<button class="button" type="submit">Save &amp; publish / schedule</button></form></section>
<section class="card" style="padding:24px"><h2>Announcement history</h2>
<div style="overflow:auto"><table><thead><tr><th>Message</th><th>Business</th><th>Status</th><th>Recipients</th><th>Read</th><th>Acknowledged</th><th>Completed</th></tr></thead><tbody>
@forelse($messages as $m)@php($n=$stats[$m->id]??null)<tr><td><strong>{{$m->title}}</strong><br><small>{{\Illuminate\Support\Str::limit($m->body,90)}}</small></td><td>{{$m->business_name??'All businesses'}}</td><td>{{$m->status}}</td><td>{{$n->recipients??0}}</td><td>{{$n->reads??0}}</td><td>{{$n->acknowledgements??0}}</td><td>{{$n->completions??0}}</td></tr>@empty<tr><td colspan="7">No announcements yet.</td></tr>@endforelse
</tbody></table></div>{{$messages->links()}}</section>
<section class="card" style="padding:24px;margin-top:20px"><h2>Recent employee feedback</h2>
@forelse($feedback as $entry)<p><strong>{{$entry->name}}</strong> · {{$entry->created_at}}<br>{{$entry->comment}}</p><hr>@empty<p>No employee feedback has been submitted yet.</p>@endforelse
</section>
<script>
async function v39LoadTargets(){
 const business=document.getElementById('v39-business');if(!business)return;
 for(const id of ['v39-branch','v39-employee'])document.getElementById(id).replaceChildren(new Option('Select '+(id==='v39-branch'?'workplace':'employee'),''));
 if(!business.value)return;
 try {
 const response=await fetch('{{url('/platform/notifications/targets')}}?business_id='+encodeURIComponent(business.value),{headers:{'Accept':'application/json'}});
 if(!response.ok)throw new Error('Unable to load the selected business');const data=await response.json();
 for(const item of data.branches)document.getElementById('v39-branch').add(new Option(item.name,item.id));
 for(const item of data.employees)document.getElementById('v39-employee').add(new Option(item.name,item.id));
 }catch(e){alert(e.message)}
}
function v39Template(k){let templates={profile:['Please update your contact details','Please open your MentoClock profile and confirm your contact email and mobile number.'],feedback:['Tell us about your experience','We would value your feedback about using MentoClock.'],review:['Share an honest review','If you have used MentoClock, you are welcome to leave an honest review in your app store.'],update:['MentoClock update available','Please update MentoClock to the latest version from your app store.'],attendance:['Attendance reminder','Remember to clock in at the start of your shift and clock out at the end.']};if(templates[k]){document.getElementById('v39-title').value=templates[k][0];document.getElementById('v39-body').value=templates[k][1];document.getElementById('v39-action').value=k}}
</script>
@endsection
