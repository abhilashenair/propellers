@extends('layout')
@section('title','Reset password')
@section('content')
<div class="auth-card"><h1>Reset password</h1><p class="subtext">Choose a password with at least 12 characters, including letters and numbers.</p><form method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label class="field">Email address<input type="email" name="email" value="{{ old('email',is_string($email)?$email:'') }}" required maxlength="255" autocomplete="email"></label><label class="field">New password<input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label class="field">Confirm new password<input type="password" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password"></label><button class="button">Reset password</button></form></div>
@endsection
