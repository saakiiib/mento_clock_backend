@extends('layout')
@section('title','Change workplace')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Employee management</span><h1>Change workplace: {{$employee->name}}</h1><p>Choose one or more workplaces. Historical attendance remains with its original workplace.</p></div></div>
<section class="card narrow"><h2>Assigned workplaces</h2>
@if($open->isNotEmpty())<div class="note">An active attendance record exists at {{$open->pluck('name')->join(', ')}}. You must keep these workplaces assigned until the employee clocks out.</div>@endif
<form method="post" action="{{url('/employees/'.$employee->id.'/assignments')}}">@csrf
@foreach($branches as $branch)<label style="display:block;padding:8px"><input type="checkbox" name="branches[]" value="{{$branch->id}}" @checked(in_array($branch->id,old('branches',$assigned)))> {{$branch->name}}</label>@endforeach
<p class="caption">Changing assignments signs the employee out of the app. Ask them to sign in again.</p><button>Save workplace assignments</button> <a class="button secondary" href="{{url('/people')}}">Cancel</a></form></section>
@endsection
