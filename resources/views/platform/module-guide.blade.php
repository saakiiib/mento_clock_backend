@extends('layout')
@section('title','Module guide')
@section('content')
<div class="page-heading"><div><span class="eyebrow">SUPER ADMIN REFERENCE</span><h1>Module guide</h1><p>Use this guide to explain each module and run a consistent client demonstration. Demonstrations should use a demo business with sample data.</p></div><div class="actions"><a class="button secondary" href="{{url('/platform/module-catalogue')}}">Manage pricing</a><a class="button secondary" href="{{url('/platform/module-requests')}}">View requests</a></div></div>
<section class="card module-guide-notice"><strong>Demo safely</strong><p>Open the relevant client workspace with its administrator account. Super Admin access does not impersonate a client. Use fictional employees and dummy documents, and confirm the modules are enabled before the demonstration.</p></section>
<section class="module-guide-search"><label for="module-guide-search">Find a module</label><input id="module-guide-search" type="search" placeholder="Search modules or demo steps" data-module-guide-search><span data-module-guide-count aria-live="polite">{{$modules->count()}} modules</span></section>
<div class="module-grid module-guide-grid">
@foreach($modules as $module)
<article class="card module-guide-card" id="module-{{$module->code}}" data-module-guide-card>
    <div class="card-head"><div><span class="eyebrow">{{$module->is_core || $module->code==='reports_analytics' ? 'CORE PLAN' : 'CLIENT MODULE'}}</span><h2>{{$module->name}}</h2></div><span class="badge {{$module->available?'':'inactive'}}">{{$module->available?'Available to assign':'Coming soon'}}</span></div>
    <p class="module-guide-purpose">{{$module->guide['purpose']}}</p>
    <div class="module-guide-section"><h3>Before the demo</h3><ul>@foreach($module->guide['prepare'] as $step)<li>{{$step}}</li>@endforeach</ul></div>
    <div class="module-guide-section"><h3>Suggested walkthrough</h3><ol>@foreach($module->guide['demo'] as $step)<li>{{$step}}</li>@endforeach</ol></div>
    <div class="module-guide-explain"><strong>How to explain it</strong><p>{{$module->guide['explain']}}</p></div>
    <p class="module-guide-route"><strong>Client workspace:</strong> {{$module->guide['route']}}</p>
    <p class="caption">{{$module->description}}</p>
</article>
@endforeach
</div>
@endsection
