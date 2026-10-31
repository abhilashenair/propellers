@extends('layout')
@section('title','Email settings')
@section('content')
<div class="page-head"><div><h1>Email settings</h1><p class="subtext">Configure SMTP delivery for password-reset emails.</p></div><a class="button secondary" href="{{ route('settings.edit') }}">Company settings</a></div>
<form method="post" action="{{ route('email-settings.update') }}">@csrf @method('PUT')<div class="panel"><div class="panel-head"><h2>SMTP configuration</h2></div><div class="panel-body form-grid">
<label class="field">Email delivery<select name="enabled"><option value="0" @selected(!old('enabled',$settings?->enabled))>Disabled</option><option value="1" @selected(old('enabled',$settings?->enabled))>Enabled</option></select></label>
<label class="field">SMTP host<input name="host" value="{{ old('host',$settings?->host) }}" placeholder="smtp.your-provider.com" required maxlength="255"></label>
<label class="field">Port<input type="number" name="port" value="{{ old('port',$settings?->port??587) }}" min="1" max="65535" required></label>
<label class="field">Encryption<select name="encryption">@foreach(['starttls'=>'STARTTLS (usually port 587)','smtps'=>'SSL / TLS (usually port 465)','none'=>'None'] as $value=>$label)<option value="{{ $value }}" @selected(old('encryption',$settings?->encryption??'starttls')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="field">SMTP username<input name="username" value="{{ old('username',$settings?->username) }}" maxlength="255" autocomplete="off"></label>
<label class="field">SMTP password<input type="password" name="password" autocomplete="new-password" maxlength="4096"><small>{{ $settings?->password?'Password saved. Leave blank to keep it.':'Enter your SMTP password or provider app password.' }}</small></label>
<label class="field">Sender email<input type="email" name="from_address" value="{{ old('from_address',$settings?->from_address) }}" required maxlength="255"></label>
<label class="field">Sender name<input name="from_name" value="{{ old('from_name',$settings?->from_name) }}" required maxlength="255"></label>
<label class="field wide">Application URL<input type="url" name="application_url" value="{{ old('application_url',$settings?->application_url??url('/')) }}" required maxlength="2048"><small>Base address used in reset links, including the folder: for example, http://192.168.31.250/propeller</small></label>
</div></div><div class="form-actions"><button class="button">Save email settings</button></div></form>
@endsection
