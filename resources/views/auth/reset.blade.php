@extends('auth.layout')
@section('content')
<div class="auth-card card"><span class="eyebrow">A FRESH START</span><h1>Set a new password</h1><p>Choose at least 6 characters. You’ll use this password in the app and portal.</p><form method="post" action="/reset-password">@csrf<input type="hidden" name="token" value="{{$token}}"><label>Email</label><input name="email" type="email" value="{{$email}}" required><label>New password</label><input name="password" type="password" minlength="6" required autocomplete="new-password"><label>Confirm password</label><input name="password_confirmation" type="password" minlength="6" required autocomplete="new-password"><button style="width:100%;margin-top:24px">Update password</button></form></div>
@endsection
