@extends('layout')
@section('title','My profile')
@section('content')
<div class="page-head"><div><h1>My profile</h1><p class="subtext">Manage your photo, display name and password.</p></div></div>
<div class="split">
<div class="panel"><div class="panel-head"><h2>Profile details</h2></div><form class="panel-body" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf @method('PUT')
<div class="form-grid"><label class="field wide">Name<input name="name" value="{{ old('name',$user->name) }}" required maxlength="255" autocomplete="name"></label><label class="field wide">Login email (cannot be changed)<input type="email" value="{{ $user->email }}" readonly aria-readonly="true"><small>Your login email is fixed and cannot be changed from your profile.</small></label>
@if($user->photo_path)<div class="field wide"><img src="{{ route('profile.photo') }}?v={{ $user->updated_at->timestamp }}" alt="Your profile photo" style="width:90px;height:90px;object-fit:cover;border-radius:50%"><label><input type="checkbox" name="remove_photo" value="1" style="width:auto"> Remove current photo</label></div>@endif
<label class="field wide">Profile photo<input type="file" name="photo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG or WebP, up to 2 MB and 2000 × 2000 pixels.</small></label></div><div class="form-actions"><button class="button">Save profile</button></div></form></div>
<div class="panel"><div class="panel-head"><h2 id="change-password">Change password</h2></div><form class="panel-body" method="post" action="{{ route('profile.password') }}">@csrf @method('PUT')<div class="form-grid"><label class="field wide">Current password<input type="password" name="current_password" required autocomplete="current-password"></label><label class="field wide">New password<input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label class="field wide">Confirm new password<input type="password" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password"></label></div><p class="subtext">Use at least 12 characters including letters and numbers.</p><div class="form-actions"><button class="button">Change password</button></div></form></div>
</div>
@endsection
