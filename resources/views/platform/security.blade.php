@extends('layout')
@section('title','Account security')
@section('content')
<div class="page-heading"><div><span class="eyebrow">Your platform account</span><h1>A little care for your access.</h1><p>Update your password. Other super admin sessions will be revoked.</p></div></div><section class="card narrow"><form method="post" action="{{url('/platform/security')}}">@csrf<label>Current password</label><input name="current_password" type="password" required autocomplete="current-password"><div class="form-two"><div><label>New password (12–72 bytes)</label><input name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></div><div><label>Confirm password</label><input name="password_confirmation" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></div></div><div class="form-actions"><button>Change password</button></div></form></section>
@endsection
