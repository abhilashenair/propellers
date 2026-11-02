@extends('layout')
@section('title','Website integration')
@section('content')
<div class="page-head"><div><h1>Website integration</h1><p class="subtext">Add shipment tracking to an external website.</p></div><a class="button secondary" href="{{ route('settings.edit') }}">Company settings</a></div>
@include('embed-code')
<div class="panel"><div class="panel-head"><h2>How to install</h2></div><div class="panel-body"><ol><li>Copy the embed code above.</li><li>Paste it into your website’s custom HTML or embed block.</li><li>Publish your page and enter an airway or reference number to check tracking.</li></ol><p class="subtext">Change the iframe height if your page needs more room. Tracking stays inside the embedded frame and does not require a customer login.</p></div></div>
@endsection
