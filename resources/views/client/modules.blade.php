@extends('layout')
@section('title','Plans & modules')
@section('content')
<div class="page-heading"><div><span class="eyebrow">BUSINESS PLAN</span><h1>Plans & modules</h1><p>Review the tools available for <strong>{{$business->name}}</strong>. Each business has its own module access.</p></div></div>
<section class="card core-plan-card"><div class="card-head"><div><span class="eyebrow">CORE PLAN</span><h2>Clock in/out & attendance reports</h2><p>Employee Management, Attendance Tracking and Dashboard & Reports.</p></div><strong class="module-price">{{$corePlan->core_plan_price_pence === null ? 'Price on request' : '£'.number_format($corePlan->core_plan_price_pence / 100,2).'/month'}}</strong></div><p>{{$corePlan->included_branches}} branch{{$corePlan->included_branches===1?'':'es'}} and up to {{$corePlan->included_employees}} employees included per business.</p><p class="caption">This business currently has {{$activeBranches}} active branch{{$activeBranches===1?'':'es'}} and {{$activeEmployees}} active employee{{$activeEmployees===1?'':'s'}}. Requests are reviewed by Mento Software; access changes only after approval.</p></section>
<div class="page-heading"><div><h2>Available modules</h2><p>Prices are monthly and shown for this business. Your final quote may reflect your agreed employee and branch limits.</p></div></div>
<div class="module-grid client-module-grid">
@foreach($modules as $module)
    @php
        $inCorePlan = (bool) $module->is_core || $module->code === 'reports_analytics';
        $isEnabled = in_array($module->id, $enabledIds, true);
        $latest = $latestRequests->get($module->id);
        $isPending = in_array($module->id, $pending, true);
        $price = $module->monthly_price_pence;
        $priceText = $price === null ? 'Price on request' : '£'.number_format(($price / 100) * ($module->price_basis === 'per_employee' ? max(1, $activeEmployees) : 1), 2).'/month';
        $basisText = $module->price_basis === 'per_employee' ? 'per active employee' : 'per business';
        $entitlement = $entitlements->get($module->id);
        $isTrial = $isEnabled && $entitlement !== null && $entitlement->entitlement_type === 'trial';
        $trialEnds = $entitlement !== null && $entitlement->ends_at
            ? \Illuminate\Support\Carbon::parse($entitlement->ends_at)->timezone('Europe/London')->format('d M Y')
            : null;
    @endphp
    <article class="card module-shop-card">
        <div class="module-shop-main"><div><span class="eyebrow">{{$inCorePlan ? 'CORE PLAN' : 'MODULE'}}</span><h3>{{$module->name}}</h3><p>{{$module->description}}</p></div><strong class="module-price">{{$inCorePlan ? 'Included' : $priceText}}</strong></div>
        @if(!$inCorePlan && $price !== null)<p class="caption">£{{number_format($price / 100, 2)}} {{$basisText}} · billed monthly</p>@endif
        <div class="module-shop-footer">
            @if($inCorePlan)
                <span class="badge">Included in core plan</span>
            @elseif(!$module->available)
                <span class="badge inactive">Coming soon</span>
            @elseif($isEnabled)
                <span class="badge">Active for this business</span>
            @elseif($isPending)
                <span class="badge">Request under review</span>
            @else
                <div class="module-request-actions">
                    <form method="post" action="{{url('/modules/'.$module->id.'/request')}}">@csrf<input type="hidden" name="desired_access" value="trial"><button class="secondary">Request a trial</button></form>
                    <form method="post" action="{{url('/modules/'.$module->id.'/request')}}">@csrf<input type="hidden" name="desired_access" value="paid"><button>Request paid access</button></form>
                </div>
                <small>Checkout is not enabled yet. We’ll confirm the agreement before charging or activating access.</small>
            @endif
            @if($latest && !$isPending && !$isEnabled)<span class="caption">Last request: {{ucfirst($latest->status)}}@if($latest->review_note) · {{$latest->review_note}}@endif</span>@endif
            @if($isTrial)
                <span class="caption">Trial access{{ $trialEnds ? ' · ends '.$trialEnds : '' }}</span>
            @endif
        </div>
    </article>
@endforeach
</div>
<section class="card"><div class="card-head"><div><h2>How module requests work</h2><p>Choose a trial or paid access request. Mento Software will review it and confirm the terms with you. Super Admin then enables the module for this business.</p></div></div><p class="caption">For a client account with multiple businesses, switch businesses from the header to review or request modules separately for each one.</p></section>
@endsection
