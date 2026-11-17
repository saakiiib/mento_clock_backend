@extends('layout')
@section('title','Module pricing')
@section('content')
<div class="page-heading"><div><span class="eyebrow">SAAS SETUP</span><h1>Module pricing</h1><p>Set the monthly prices and billing basis shown to business administrators. Leave a price blank to show “Price on request”.</p></div><a class="button secondary" href="{{url('/platform/module-requests')}}">Review module requests</a></div>
<section class="card"><div class="card-head"><div><span class="eyebrow">CORE PLAN</span><h2>Clock in/out & attendance reports</h2><p>Includes Employee Management, Attendance Tracking and Dashboard & Reports for each business.</p></div></div><form method="post" action="{{url('/platform/module-catalogue/core-plan')}}">@csrf<div class="form-three"><div><label>Monthly price (£ / business)</label><input name="monthly_price" type="number" min="0" max="99999.99" step="0.01" value="{{$corePlan->core_plan_price_pence === null ? '' : number_format($corePlan->core_plan_price_pence / 100,2,'.','')}}"></div><div><label>Branches included</label><input name="included_branches" type="number" min="1" max="500" value="{{$corePlan->included_branches}}" required></div><div><label>Employees included</label><input name="included_employees" type="number" min="1" max="500" value="{{$corePlan->included_employees}}" required></div></div><div class="form-actions"><button>Save core plan</button></div></form></section>
<div class="module-grid">
@foreach($modules as $module)
@php($inCorePlan = (bool) $module->is_core || $module->code === 'reports_analytics')
<section class="card module-price-admin-card"><div class="card-head"><div><h2>{{$module->name}}</h2><p>{{$module->description}}</p></div><span class="badge {{$inCorePlan?'':($module->available?'':'inactive')}}">{{$inCorePlan?'Core plan':($module->available?'Requestable':'Coming soon')}}</span></div>
@if(!$inCorePlan)
<form method="post" action="{{url('/platform/module-catalogue/'.$module->id)}}">@csrf<div class="form-two"><div><label>Monthly price (£)</label><input name="monthly_price" type="number" min="0" max="99999.99" step="0.01" value="{{$module->monthly_price_pence === null ? '' : number_format($module->monthly_price_pence / 100, 2, '.', '')}}" placeholder="Leave blank for quote"></div><div><label>Price basis</label><select name="price_basis"><option value="per_business" @selected($module->price_basis === 'per_business')>Per business</option><option value="per_employee" @selected($module->price_basis === 'per_employee')>Per active employee</option></select></div></div><div class="form-actions"><button>Save price</button></div></form>
@else
<p class="caption">Included in the clock-in/out and attendance reports core plan.</p>
@endif
</section>
@endforeach
</div>
<section class="card"><p class="caption">Prices are displayed as monthly estimates in GBP. Checkout and automatic recurring billing are not connected in this release; paid requests require manual review and confirmation.</p></section>
@endsection
