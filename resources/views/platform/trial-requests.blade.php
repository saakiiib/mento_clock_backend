@extends('layout')
@section('title','Trial requests')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Super Admin · Sales enquiries</span><h1>Private trial requests</h1><p>Review businesses asking to explore MentoClock, follow up with the owner and track each request.</p></div></div>
<div class="stats">
<article class="card stat"><div class="stat-head">NEW</div><div class="number">{{$stats['new']}}</div><small>Waiting for follow-up</small></article>
<article class="card stat"><div class="stat-head">CONTACTED</div><div class="number">{{$stats['contacted']}}</div><small>Follow-up started</small></article>
<article class="card stat"><div class="stat-head">CLOSED</div><div class="number">{{$stats['closed']}}</div><small>Completed or not proceeding</small></article>
<article class="card stat"><div class="stat-head">TOTAL TRIAL REQUESTS</div><div class="number">{{$stats['total']}}</div><small>All recorded requests</small></article>
</div>
<section class="card"><div class="card-head"><div><h2>Trial enquiry list</h2><p>Most recent requests appear first. Contact details are visible only to Super Admins.</p></div><a class="button secondary" href="{{url('/platform/trial-requests/export')}}">Download CSV</a></div>
@forelse($enquiries as $enquiry)
<details class="disclosure trial-enquiry"><summary><span><strong>{{$enquiry->company}}</strong> · {{$enquiry->name}}</span><span class="badge {{$enquiry->status==='new'?'':'neutral'}}">{{$enquiry->status}}</span></summary>
<div class="profile-details">
<div><dt>Work email</dt><dd><a href="mailto:{{$enquiry->email}}">{{$enquiry->email}}</a></dd></div>
<div><dt>Phone</dt><dd>@if($enquiry->phone)<a href="tel:{{$enquiry->phone}}">{{$enquiry->phone}}</a>@else Not provided @endif</dd></div>
<div><dt>Team size</dt><dd>{{$enquiry->employees}}</dd></div>
<div><dt>Workplaces</dt><dd>{{$enquiry->branches}}</dd></div>
<div><dt>Received</dt><dd>{{Carbon\Carbon::parse($enquiry->created_at,'UTC')->timezone(config('app.timezone','UTC'))->format('d M Y, H:i')}}</dd></div>
<div><dt>Consent recorded</dt><dd>{{$enquiry->consented_at ? Carbon\Carbon::parse($enquiry->consented_at,'UTC')->timezone(config('app.timezone','UTC'))->format('d M Y, H:i') : 'Not recorded'}}</dd></div>
</div>
<div class="actions request-actions"><form method="post" action="{{url('/platform/trial-requests/'.$enquiry->id)}}">@csrf<label>Status<select name="status">@foreach(['new','contacted','closed'] as $status)<option value="{{$status}}" @selected($enquiry->status===$status)>{{$status}}</option>@endforeach</select></label><button class="secondary">Save status</button></form><form method="post" action="{{url('/platform/trial-requests/'.$enquiry->id.'/delete')}}" data-confirm="Delete this trial request permanently?">@csrf<button class="danger">Delete request</button></form></div>
</details>
@empty
<p class="caption">No private trial requests yet.</p>
@endforelse
</section>
<p class="caption">Requests are retained for 90 days and then removed by the scheduled cleanup.</p>
@endsection
