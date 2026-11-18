@extends('layout')
@section('title','Module requests')
@section('content')
<div class="page-heading"><div><span class="eyebrow">CLIENT REQUESTS</span><h1>Module requests</h1><p>Review requests by business, then approve trial or paid access. Approval enables the module only for that business.</p></div><a class="button secondary" href="{{url('/platform/module-catalogue')}}">Manage pricing</a></div>
<?php if ($requests->count() > 0): ?>
<?php foreach ($requests as $item): ?>
<section class="card module-request-card"><div class="card-head"><div><span class="eyebrow">{{$item->account_name ?: 'Business account'}}</span><h2>{{$item->business_name}} · {{$item->module_name}}</h2><p>Requested by {{$item->requester_name}} · {{$item->requester_email}} · {{ucfirst($item->desired_access)}} access requested</p></div><span class="badge {{$item->status==='pending'?'':'inactive'}}">{{ucfirst($item->status)}}</span></div>
@if($item->request_note)<p><strong>Client note:</strong> {{$item->request_note}}</p>@endif
@if($item->status === 'pending')
<form method="post" action="{{url('/platform/module-requests/'.$item->id)}}" class="module-review-form">@csrf<div class="form-two"><div><label>Decision</label><select name="decision" required><option value="approved">Approve</option><option value="rejected">Decline</option></select></div><div><label>Access type if approved</label><select name="granted_type"><option value="trial">Trial</option><option value="paid">Paid · manual confirmation</option></select></div></div><div class="form-two"><div><label>Trial end date (required for trial)</label><input type="date" name="trial_ends_at" min="{{now('Europe/London')->addDay()->toDateString()}}"></div><div><label>Review note (optional)</label><input name="review_note" maxlength="1000"></div></div><label class="module-paid-confirm"><input type="checkbox" name="paid_confirmed" value="1"> I have confirmed the paid agreement before enabling access.</label><p class="caption">Paid access is enabled manually after you confirm the agreed payment arrangement. No payment is taken by this portal.</p><div class="form-actions"><button>Save decision</button></div></form>
@else
<p class="caption">Reviewed {{\Illuminate\Support\Carbon::parse($item->reviewed_at)->format('d M Y')}}@if($item->reviewer_name) by {{$item->reviewer_name}}@endif @if($item->granted_type) · {{$item->granted_type==='trial'?'Trial':'Paid'}} access@endif @if($item->trial_ends_at) · trial ends {{\Illuminate\Support\Carbon::parse($item->trial_ends_at)->format('d M Y')}}@endif</p>@if($item->review_note)<p>{{$item->review_note}}</p>@endif
@endif
</section>
<?php endforeach; else: ?>
<section class="card empty-state"><h2>No module requests yet</h2><p>Requests submitted by business administrators will appear here.</p></section>
<?php endif; ?>
<div class="pagination-wrap">{{$requests->links()}}</div>
@endsection
