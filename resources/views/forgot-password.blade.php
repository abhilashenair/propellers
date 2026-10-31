@extends('layout')
@section('title','Forgot password')
@section('content')
<div class="auth-card"><h1>Forgot your password?</h1><p class="subtext">Enter your account email to request a password-reset link.</p><form method="post" action="{{ route('password.email') }}">@csrf<label class="field">Email address<input type="email" name="email" required value="{{ old('email') }}" autocomplete="email" maxlength="255"></label><button class="button">Send reset link</button></form><p><a class="link" href="{{ route('login') }}">← Back to sign in</a></p></div>
@endsection
