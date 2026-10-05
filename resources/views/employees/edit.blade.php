@extends('layout')
@section('content')
<a href="/people">← Back to people</a><h1>Edit employee</h1><p>Update account details, emergency contact and assigned workplaces.</p>
<div class="card" style="max-width:680px"><form method="post" action="/employees/{{$employee->id}}/edit">@csrf
<label>Name</label><input name="name" value="{{old('name',$employee->name)}}" required maxlength="100">
<label>Work email</label><input name="email" type="email" value="{{old('email',$employee->email)}}" required maxlength="255">
<label>Phone</label><input name="phone" value="{{old('phone',$employee->phone)}}" maxlength="40">
<label>Employee reference</label><input name="employee_code" value="{{old('employee_code',$employee->employee_code)}}" maxlength="40">
<div class="form-two"><div><label>Job title</label><input name="job_title" value="{{old('job_title',$employee->job_title)}}" maxlength="100"></div><div><label>Employment start date</label><input name="employment_start_date" type="date" value="{{old('employment_start_date',$employee->employment_start_date ? Carbon\Carbon::parse($employee->employment_start_date)->format('Y-m-d') : '')}}"></div></div>
<div class="form-two"><div><label>Emergency contact name</label><input name="emergency_contact_name" value="{{old('emergency_contact_name',$employee->emergency_contact_name)}}" maxlength="100"></div><div><label>Emergency contact phone</label><input name="emergency_contact_phone" value="{{old('emergency_contact_phone',$employee->emergency_contact_phone)}}" maxlength="40"></div></div>
<label>Assigned workplaces · employee capacity</label><div class="checks">@foreach($branches as $b)@php $selected=in_array($b->id,$assigned); $used=$b->employee_count-($selected && $employee->active ? 1 : 0); $full=$used >= $b->employee_limit; @endphp<label><input type="checkbox" name="branches[]" value="{{$b->id}}" @checked($selected) @disabled($full&&!$selected)> {{$b->name}} · {{$b->employee_count}}/{{$b->employee_limit}} @if($full&&!$selected)(Full)@endif</label>@endforeach</div>
<button style="margin-top:24px">Save employee</button></form></div>
@endsection
