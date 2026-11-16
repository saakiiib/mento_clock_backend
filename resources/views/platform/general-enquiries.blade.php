@extends('layout')
@section('title','General enquiries')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Super Admin · Website enquiries</span><h1>General enquiries</h1><p>Review messages sent through the website contact form, contact the business owner and track follow-up.</p></div></div>
<div class="stats">
<article class="card stat"><div class="stat-head">NEW</div><div class="number">{{$stats['new']}}</div><small>Waiting for follow-up</small></article>
<article class="card stat"><div class="stat-head">CONTACTED</div><div class="number">{{$stats['contacted']}}</div><small>Follow-up started</small></article>
<article class="card stat"><div class="stat-head">CLOSED</div><div class="number">{{$stats['closed']}}</div><small>Completed or not proceeding</small></article>
<article class="card stat"><div class="stat-head">TOTAL ENQUIRIES</div><div class="number">{{$stats['total']}}</div><small>General website enquiries</small></article>
</div>
<section class="card"><div class="card-head"><div><h2>Enquiry list</h2><p>Most recent messages appear first. Contact details are visible only to Super Admins.</p></div><a class="button secondary" href="{{url('/platform/general-enquiries/export')}}">Download CSV</a></div>
@forelse($enquiries as $enquiry)
<details class="disclosure general-enquiry"><summary><span><strong>{{$enquiry->company}}</strong> · {{$enquiry->name}}</span><span class="badge {{$enquiry->status==='new'?'':'neutral'}}">{{$enquiry->status}}</span></summary>
<div class="profile-details">
<div><dt>Email</dt><dd><a href="mailto:{{$enquiry->email}}">{{$enquiry->email}}</a></dd></div>
<div><dt>Phone</dt><dd>@if($enquiry->phone)<a href="tel:{{$enquiry->phone}}">{{$enquiry->phone}}</a>@else Not provided @endif</dd></div>
<div><dt>Team size</dt><dd>{{$enquiry->employees}}</dd></div>
<div><dt>Workplaces</dt><dd>{{$enquiry->branches}}</dd></div>
<div><dt>Received</dt><dd>{{Carbon\Carbon::parse($enquiry->created_at,'UTC')->timezone(config('app.timezone','UTC'))->format('d M Y, H:i')}}</dd></div>
</div>
<div class="general-enquiry-message"><strong>Message</strong><p>{{ $enquiry->message }}</p></div>
<div class="actions request-actions"><form method="post" action="{{url('/platform/general-enquiries/'.$enquiry->id)}}">@csrf<label>Status<select name="status">@foreach(['new','contacted','closed'] as $status)<option value="{{$status}}" @selected($enquiry->status===$status)>{{$status}}</option>@endforeach</select></label><button class="secondary">Save status</button></form><form method="post" action="{{url('/platform/general-enquiries/'.$enquiry->id.'/delete')}}" data-confirm="Delete this general enquiry permanently?">@csrf<button class="danger">Delete enquiry</button></form></div>
</details>
@empty
<p class="caption">No general enquiries yet.</p>
@endforelse
</section>
<p class="caption">General enquiries are retained for 90 days and then removed by the scheduled cleanup.</p>
@endsection
