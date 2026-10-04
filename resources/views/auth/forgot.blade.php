@extends('layout')
@section('content')
<div class="auth-card card"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>Forgot your password?</h1><p>Enter your work email. We’ll send a secure reset link if it matches an account.</p><form method="post" action="/forgot-password">@csrf<label>Work email</label><input name="email" type="email" required autocomplete="email"><button style="width:100%;margin-top:24px">Send reset link</button></form><p><a href="/login">Back to sign in</a></p></div>
@endsection
