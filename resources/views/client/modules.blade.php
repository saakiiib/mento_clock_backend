@extends('layout')
@section('title','Plans & modules')
@section('content')
@php
    $includedBranches = (int) ($corePlan->included_branches ?? 1);
    $extraBranchCount = max(0, $activeBranches - $includedBranches);
    $capacityBranches = max($includedBranches, $activeBranches);
    $coreExtraCount = max(0, $activeEmployees - ($capacityBranches * $includedEmployees));
    $extraBranchPrice = $corePlan->core_extra_branch_price_pence ?? null;
    $coreStandardPrice = ($corePlan->core_plan_price_pence === null || ($extraBranchCount > 0 && $extraBranchPrice === null) || ($coreExtraCount > 0 && $corePlan->core_extra_employee_price_pence === null))
        ? null
        : $corePlan->core_plan_price_pence + ($extraBranchPrice !== null ? $extraBranchCount * $extraBranchPrice : 0) + ($corePlan->core_extra_employee_price_pence !== null ? $coreExtraCount * $corePlan->core_extra_employee_price_pence : 0);
    $coreEstimate = $coreStandardPrice === null ? null : (int) round($coreStandardPrice * (100 - $coreDiscountPercent) / 100);
@endphp
<div class="page-heading"><div><span class="eyebrow">BUSINESS PLAN</span><h1>Plans & modules</h1><p>Pricing and access for <strong>{{$business->name}}</strong>. Each business has its own module access and requests.</p></div></div>
<section class="card core-plan-card"><div class="card-head"><div><span class="eyebrow">CORE PLAN · THREE MODULES INCLUDED</span><h2>Clock in/out & attendance reports</h2><p>Employee Management · Attendance Tracking · Dashboard & Reports</p></div><div class="module-price">@if($coreEstimate === null)Price on request @else<strong>£{{number_format($coreEstimate / 100,2)}}/month</strong>@if($coreDiscountPercent > 0)<small>Standard £{{number_format($coreStandardPrice / 100,2)}} · {{$coreDiscountPercent}}% agreed discount</small>@endif @endif</div></div>
<p>{{$includedBranches}} branch{{$includedBranches===1?'':'es'}} and up to {{$includedEmployees}} employees per branch included. @if($extraBranchPrice !== null) Each additional branch: £{{number_format($extraBranchPrice / 100,2)}}/month, including up to {{$includedEmployees}} employees. @else Additional branch pricing is agreed by quote. @endif @if($corePlan->core_extra_employee_price_pence !== null)Employees above the included branch capacity: £{{number_format($corePlan->core_extra_employee_price_pence / 100,2)}} each/month.@endif</p>
<p class="caption">This business has {{$activeBranches}} active branch{{$activeBranches===1?'':'es'}} and {{$activeEmployees}} active employee{{$activeEmployees===1?'':'s'}}. @if($coreStandardPrice !== null)@if($extraBranchCount > 0)Standard estimate: £{{number_format($coreStandardPrice / 100,2)}} ({{$extraBranchCount}} additional branch{{$extraBranchCount===1?'':'es'}}). @else Standard estimate: £{{number_format($coreStandardPrice / 100,2)}}. @endif @if($coreDiscountPercent > 0)Agreed {{$coreDiscountPercent}}% discount applied: £{{number_format($coreEstimate / 100,2)}}/month. @endif @if($coreExtraCount > 0)Includes {{$coreExtraCount}} employee{{$coreExtraCount===1?'':'s'}} above branch capacity. @endif @endif Monthly GBP estimate, before any applicable VAT; your agreed quote confirms the final amount.</p></section>
<div class="page-heading"><div><h2>Add-on module prices</h2><p>Optional modules are priced separately. Any per-employee top-up rates are shown on the relevant module card.</p></div></div>
<div class="module-grid client-module-grid">
@foreach($modules as $module)
    @php
        $inCorePlan = (bool) $module->is_core || $module->code === 'reports_analytics';
        $isEnabled = in_array($module->id, $enabledIds, true);
        $latest = $latestRequests->get($module->id);
        $isPending = in_array($module->id, $pending, true);
        $basePrice = $module->included_price_pence;
        $extraPrice = $module->extra_employee_price_pence;
        $isTiered = $module->pricing_model === 'tiered';
        $extraCount = max(0, $activeEmployees - $includedEmployees);
        $estimatedPrice = $basePrice === null ? null : $basePrice + ($isTiered && $extraPrice !== null ? $extraCount * $extraPrice : 0);
        $entitlement = $entitlements->get($module->id);
        $isTrial = $isEnabled && $entitlement !== null && $entitlement->entitlement_type === 'trial';
        $trialEnds = $entitlement !== null && $entitlement->ends_at
            ? \Illuminate\Support\Carbon::parse($entitlement->ends_at)->timezone('Europe/London')->format('d M Y')
            : null;
    @endphp
    <article class="card module-shop-card">
        <div class="module-shop-main"><div><span class="eyebrow">{{$inCorePlan ? 'CORE PLAN' : 'ADD-ON MODULE'}}</span><h3>{{$module->name}}</h3><p>{{$module->description}}</p></div><strong class="module-price">{{$inCorePlan ? 'Included' : ($estimatedPrice === null ? 'Price on request' : '£'.number_format($estimatedPrice / 100,2).'/month')}}</strong></div>
        @if(!$inCorePlan && $basePrice !== null)
            <p class="caption">@if($isTiered)£{{number_format($basePrice / 100,2)}}/month includes up to {{$includedEmployees}} active employees@if($extraPrice !== null); then £{{number_format($extraPrice / 100,2)}} per additional active employee/month@endif. @else£{{number_format($basePrice / 100,2)}}/month per business. @endif</p>
            @if($isTiered && $extraCount > 0)<p class="caption">Estimate for this business: base price plus {{$extraCount}} additional active employee{{$extraCount===1?'':'s'}}.</p>@endif
        @endif
        <div class="module-shop-footer">
            @if($inCorePlan)
                <span class="badge">Included in core plan</span>
            @elseif(!$module->available)
                <span class="badge inactive">Coming soon · ask us about availability</span>
            @elseif($isEnabled)
                <span class="badge">Active for this business</span>
            @elseif($isPending)
                <span class="badge">Request under review</span>
            @else
                <div class="module-request-actions">
                    <form method="post" action="{{url('/modules/'.$module->id.'/request')}}">@csrf<input type="hidden" name="desired_access" value="trial"><button class="secondary">Request a trial</button></form>
                </div>
                <small>Trial requests go to Mento Software for review. Paid activation is arranged directly with us; checkout is not available in this portal.</small>
            @endif
            @if($latest && !$isPending && !$isEnabled)<span class="caption">Last request: {{ucfirst($latest->status)}}@if($latest->review_note) · {{$latest->review_note}}@endif</span>@endif
            @if($isTrial)<span class="caption">Trial access{{ $trialEnds ? ' · ends '.$trialEnds : '' }}</span>@endif
        </div>
    </article>
@endforeach
</div>
<section class="card"><div class="card-head"><div><h2>How module requests work</h2><p>Request a trial for an add-on module. Mento Software reviews it before Super Admin enables access. Paid subscriptions are arranged directly with us; this portal does not take payment.</p></div></div><p class="caption">For an account with multiple businesses, switch businesses from the header to view pricing, access and trial requests separately. Prices are monthly estimates in GBP, before any applicable VAT; your agreed quote confirms the final amount.</p></section>
@endsection
