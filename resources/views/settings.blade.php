@extends('layout')
@section('title', 'Settings')
@section('content')
<div class="page-head"><div><h1>Company settings</h1><p class="subtext">Manage the company details displayed on airway bills and printed copies.</p></div><div style="display:flex;gap:10px;flex-wrap:wrap"><a class="button secondary" href="{{ route('template-settings.edit') }}">Template settings →</a><a class="button secondary" href="{{ route('email-settings.edit') }}">Email settings →</a><a class="button secondary" href="{{ route('website-integration.index') }}">Website integration →</a></div></div>
<form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="panel">
        <div class="panel-head"><h2>Company details</h2></div>
        <div class="panel-body form-grid">
            <label class="field">Company name<input name="name" value="{{ old('name', $company?->name) }}" required maxlength="255" autocomplete="organization"></label>
            <label class="field">GSTIN<input name="gstin" value="{{ old('gstin', $company?->gstin) }}" maxlength="15" style="text-transform:uppercase"><small>15-character GST identification number; leave blank if not applicable.</small></label>
            <label class="field wide">Company address<textarea name="address" required maxlength="2000" autocomplete="street-address">{{ old('address', $company?->address) }}</textarea></label>
            <label class="field wide">Company logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG or WebP. Maximum 2 MB and 2000 × 2000 pixels. Leave empty to keep the current logo.</small></label>
            @if($company?->logo_path)
                <div class="field wide"><span>Current logo</span><img src="{{ route('company.logo') }}?v={{ $company->updated_at->timestamp }}" alt="Current company logo" style="max-width:180px;max-height:90px;object-fit:contain;align-self:flex-start"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo')) style="width:auto"> Remove current logo</label></div>
            @endif
        </div>
    </div>
    <div class="form-actions"><button class="button">Save company details</button></div>
</form>
@endsection
