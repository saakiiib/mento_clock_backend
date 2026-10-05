@extends('auth.layout')
@section('title','Sign in')
@section('content')
<span class="eyebrow">Welcome back</span><h2>Sign in to your workspace.</h2><p class="auth-intro">Your people, workplaces and attendance, together in one clear view.</p>
<form method="post" action="{{url('/login')}}" class="auth-form">@csrf
<label for="email">Work email</label><input id="email" name="email" type="email" value="{{old('email')}}" required autocomplete="username" placeholder="you@yourbusiness.com" autofocus>
<div class="label-row"><label for="password">Password</label><a href="{{url('/forgot-password')}}">Forgot password?</a></div><div class="password-field"><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password"><button type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">@include('partials.icon',['name'=>'eye'])</button></div>
<button class="button full" type="submit">Sign in @include('partials.icon',['name'=>'arrow'])</button></form>
<div class="auth-reassurance">@include('partials.icon',['name'=>'shield'])<p>Use the account provided by your employer or Mento Software.</p></div>
@endsection
