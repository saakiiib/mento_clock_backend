@extends('layout')
@section('content')
<div class="card" style="max-width:440px;margin:50px auto"><span class="eyebrow">WELCOME BACK</span><h1>Your team.<br>Your time.</h1><p>Sign in to manage attendance across your workplaces.</p><form method="post" action="/login">@csrf<label>Work email</label><input name="email" type="email" required autocomplete="username"><label>Password</label><input name="password" type="password" required autocomplete="current-password"><button style="width:100%;margin-top:24px">Sign in</button></form></div>
@endsection
