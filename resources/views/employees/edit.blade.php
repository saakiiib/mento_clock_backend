@extends('layout')
@section('title','Edit employee')
@section('content')
<div class="page-heading"><div><span class="eyebrow">PEOPLE</span><h1>Edit employee profile</h1><p>Update personal and employment details for {{$employee->name}}.</p></div><a class="button secondary" href="{{url('/people')}}">Back to people</a></div>
@include('employees.form',['creating'=>false,'action'=>url('/employees/'.$employee->id.'/edit')])
@endsection
