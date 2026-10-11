@extends('layout')
@section('title','Add employee')
@section('content')
<div class="page-heading"><div><span class="eyebrow">PEOPLE</span><h1>Add employee</h1><p>Create an employee account and keep their work profile in one place.</p></div><a class="button secondary" href="{{url('/people')}}">Back to people</a></div>
@include('employees.form',['creating'=>true,'action'=>url('/employees'),'employee'=>null,'assigned'=>[]])
@endsection
