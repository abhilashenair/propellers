<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Shipment tracking</title><link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}"></head>
<body class="tracking-widget"><main>
<header class="widget-header">@if($company?->logo_path)<img src="{{ route('tracking.embed-logo') }}?v={{ $company->updated_at->timestamp }}" alt="{{ $company->name }} logo" class="company-brand-logo">@elseif($company)<strong>{{ $company->name }}</strong>@endif<h1>Track your shipment</h1><p class="subtext">Enter your airway number to see the latest movement updates.</p></header>
@if($errors->any())<div class="errors" role="alert">Enter a valid airway number of up to 60 characters.</div>@endif
<form class="search widget-search" method="get" action="{{ route('tracking.embed') }}"><label class="field" for="widget-number">Airway number<input id="widget-number" name="number" value="{{ $data['number']??'' }}" required maxlength="60" placeholder="Enter airway number" autocomplete="off"></label><button class="button">Track shipment</button></form>
@if(isset($data['number']))
@if($bill)<section class="panel"><div class="panel-head"><div><h2>{{ $bill->number }}</h2><p>{{ $bill->origin }} → {{ $bill->destination }} · {{ $bill->vendor->name }}</p></div><span class="badge">{{ $bill->updates->first()?->status??'Booked' }}</span></div><div class="panel-body">@include('timeline',['updates'=>$bill->updates])</div></section>
@else<div class="empty"><strong>Shipment not found</strong>Check your airway number and try again.</div>@endif
@endif
</main></body></html>
