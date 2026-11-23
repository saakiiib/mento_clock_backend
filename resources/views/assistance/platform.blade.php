@extends('layout')
@section('title','Client assistance')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Super Admin · Client success</span><h1>Client Assistance Centre</h1><p>Support your customers without accessing their passwords. Every assisted change requires a reason and creates an audit entry.</p></div></div>
@if(session('status'))<div class="note" role="status">{{session('status')}}</div>@endif
@if($errors->any())<div class="note" role="alert">{{$errors->first()}}</div>@endif
<section class="card" style="border-left:5px solid #f97316; margin-bottom:20px">
 <div class="card-head"><div><span class="eyebrow">Support inbox</span><h2>Client assistance requests</h2><p>Review new requests before assigning an operator.</p></div><div style="text-align:right"><div class="number" style="font-size:36px">{{$pendingCount}}</div><small>Pending requests</small></div></div>
 @if($pendingCount>0)<a class="button" href="#assistance-requests">Review pending requests</a>@else<p class="caption">No pending client requests. You can still assist businesses that have enabled ongoing support.</p>@endif
</section>
<section class="card"><div class="card-head"><div><h2>Manage Clients</h2><p>Direct assistance is available only where the business administrator has enabled ongoing support. Disabled by default; the client can revoke it anytime.</p></div></div>
<div class="scroll"><table><thead><tr><th>Business</th><th>Ongoing permission</th><th>Action</th></tr></thead><tbody>
@forelse($businesses as $b)
<tr><td><strong>{{$b->name}}</strong></td><td>@if($b->granted_at && !$b->revoked_at)<span class="badge">Enabled by client</span>@else<span class="badge inactive">Not enabled</span>@endif</td><td>@if($b->granted_at && !$b->revoked_at)<a class="button" href="{{url('/platform/assist/manage/'.$b->id)}}">Manage Client</a>@else<small>Ask client to enable access under Request support, or use a one-time request below.</small>@endif</td></tr>
@empty<tr><td colspan="3">No active businesses.</td></tr>@endforelse
</tbody></table></div></section>
<section class="card" id="assistance-requests"><h2>Client-approved temporary sessions</h2><p>These remain available when clients prefer approval for one specific task.</p><div class="scroll"><table><thead><tr><th>Business</th><th>Reason</th><th>Status</th><th>Operator</th><th>Expires</th><th>Action</th></tr></thead><tbody>
@forelse($sessions as $s)
@php $valid=$s->status==='active' && $s->expires_at && \Carbon\Carbon::parse($s->expires_at)->isFuture(); @endphp
<tr><td>{{$s->business_name}}</td><td>{{$s->purpose}}</td><td>{{$valid?'active':($s->status==='active'?'expired':$s->status)}}</td><td>{{$s->operator_name??'Not assigned'}}</td><td>{{$s->expires_at??'—'}}</td><td>
@if($s->status==='pending')
<form method="post" action="{{url('/platform/assist/sessions/'.$s->id.'/activate')}}">@csrf
<label>Assign operator</label><select name="operator_id" required>@foreach($operators as $o)@if(!DB::table('platform_support_operators')->where('admin_id',auth('platform')->id())->exists() || $o->id===auth('platform')->id())<option value="{{$o->id}}" @selected($o->id===auth('platform')->id())>{{$o->name}}</option>@endif @endforeach</select>
<label>Hours (max 24)</label><input name="hours" type="number" value="2" min="1" max="24" required><button>Activate</button></form>
@elseif($valid && (int)$s->operator_id===(int)auth('platform')->id())<a class="button secondary" href="{{url('/platform/assist/sessions/'.$s->id)}}">Open session</a>
@elseif($valid)<small>Assigned to {{$s->operator_name}}</small>@else<small>No action available</small>@endif
</td></tr>
@empty<tr><td colspan="6">No assistance requests yet.</td></tr>@endforelse
</tbody></table></div></section>
@if(!DB::table('platform_support_operators')->where('admin_id',auth('platform')->id())->exists())
<section class="card"><h2>Support Operator permissions</h2><p>Separate from client assistance. Restricted operators cannot access billing, system administration, or standing-access management.</p>
@php $otherOperators=$operators->filter(fn($x)=>(int)$x->id!==(int)auth('platform')->id()); @endphp
@if($otherOperators->count())<form method="post" action="{{url('/platform/assist/operators')}}">@csrf<label>Platform administrator</label><select name="admin_id" required><option value="" disabled selected>Select an existing platform administrator</option>@foreach($otherOperators as $o)<option value="{{$o->id}}">{{$o->name}} ({{$o->email}}) {{$o->is_operator?'– current operator':''}}</option>@endforeach</select><label>Role</label><select name="is_operator"><option value="1">Restricted Support Operator</option><option value="0">Full Super Admin</option></select><button>Update role and invalidate sessions</button></form>
@else<p class="caption">No other platform administrator accounts are available. Create a separate support account before assigning this role.</p>@endif
<div class="note">Do not change your own Super Admin role. Changes invalidate the target operator's sessions.</div></section>
@endif
@endsection
