@extends('layout')
@section('title','Template settings')
@section('content')
<div class="page-head"><div><h1>Template settings</h1><p class="subtext">Manage the terms and conditions shown on airway bills and printable PDFs.</p></div></div>
<form method="post" action="{{ route('template-settings.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-head"><h2>Bill terms and conditions</h2></div><div class="panel-body"><label class="field">Terms and Conditions — Description<textarea name="terms_and_conditions" rows="12" maxlength="20000" placeholder="Enter your terms and conditions, one paragraph or item per line.">{{ old('terms_and_conditions',$settings?->terms_and_conditions) }}</textarea><small>Line breaks are preserved in the PDF. These terms are saved with each new bill. Older bills without saved terms use this template. Leave blank to omit the section.</small></label></div></div>
<div class="form-actions"><button class="button">Save template settings</button></div></form>
@endsection
