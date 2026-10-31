<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title','Overview') · {{ $branding?->name ?? 'Propeller Logistics' }}</title><link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}"></head>
<body>
@auth
<aside class="sidebar">
<a class="brand" href="{{ route('dashboard') }}" aria-label="Company dashboard">
@if($branding?->logo_path)<img src="{{ route('company.logo') }}?v={{ $branding->updated_at->timestamp }}" alt="{{ $branding->name }} logo" class="company-brand-logo">
@elseif($branding)<span class="company-brand-name">{{ $branding->name }}</span>
@else<span class="brand-mark">↗</span> propeller<span class="brand-dot">.</span>@endif
</a>
<div class="workspace">LOGISTICS WORKSPACE</div>
<nav aria-label="Main navigation">
<a class="{{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><span class="nav-icon" aria-hidden="true">◫</span><span class="nav-label">Overview</span></a>
<details class="nav-group" name="workspace-navigation" @if(request()->routeIs('bills.*','shipments.*')) open @endif><summary><span class="nav-icon" aria-hidden="true">▤</span><span class="nav-label">Consignments</span></summary><div class="nav-children"><a href="{{ route('bills.create') }}">Create airway bill</a><a class="{{ request()->routeIs('bills.index','bills.show')?'active':'' }}" href="{{ route('bills.index') }}">Airway bills</a><a class="{{ request()->routeIs('shipments.*')?'active':'' }}" href="{{ route('shipments.index') }}">Airway numbers</a></div></details>
<details class="nav-group" name="workspace-navigation" @if(request()->routeIs('customers.*')) open @endif><summary><span class="nav-icon" aria-hidden="true">♙</span><span class="nav-label">Customers</span></summary><div class="nav-children"><a href="{{ route('customers.create') }}">Create customer</a><a href="{{ route('customers.index') }}">View customers</a></div></details>
<details class="nav-group" name="workspace-navigation" @if(request()->routeIs('vendors.*')) open @endif><summary><span class="nav-icon" aria-hidden="true">◇</span><span class="nav-label">Logistics partners</span></summary><div class="nav-children"><a href="{{ route('vendors.create') }}">Create vendor</a><a href="{{ route('vendors.index') }}">View vendors</a></div></details>
<details class="nav-group" name="workspace-navigation" @if(request()->routeIs('profile.*')) open @endif><summary><span class="nav-icon" aria-hidden="true">♙</span><span class="nav-label">My profile</span></summary><div class="nav-children"><a href="{{ route('profile.edit') }}">Profile & photo</a><a href="{{ route('profile.edit') }}#change-password">Change password</a></div></details>
@can('admin')
<div class="workspace">ADMINISTRATION</div>
<details class="nav-group" name="workspace-navigation" @if(request()->routeIs('booking-options.*')) open @endif><summary><span class="nav-icon" aria-hidden="true">◇</span><span class="nav-label">Booking options</span></summary><div class="nav-children">@foreach(\App\Models\BookingOption::TYPES as $type=>$label)<a href="{{ route('booking-options.index',['type'=>$type]) }}">{{ $label }}</a>@endforeach</div></details>
<details class="nav-group" name="workspace-navigation" @if(request()->routeIs('staff.*')) open @endif><summary><span class="nav-icon" aria-hidden="true">♙</span><span class="nav-label">Staff & access</span></summary><div class="nav-children"><a href="{{ route('staff.index') }}">Manage staff</a></div></details>
<details class="nav-group" name="workspace-navigation" @if(request()->routeIs('settings.*','email-settings.*','website-integration.*')) open @endif><summary><span class="nav-icon" aria-hidden="true">⚙</span><span class="nav-label">Settings</span></summary><div class="nav-children"><a href="{{ route('settings.edit') }}">Company details</a><a href="{{ route('email-settings.edit') }}">Email settings</a><a href="{{ route('website-integration.index') }}">Website integration</a></div></details>
<details class="nav-group" name="workspace-navigation"><summary><span class="nav-icon" aria-hidden="true">↓</span><span class="nav-label">Backups</span></summary><div class="nav-children"><form action="{{ route('backup') }}" method="post">@csrf<button class="nav-button">Download SQL backup</button></form></div></details>
@endcan
</nav><div class="sidebar-bottom"><a class="avatar" href="{{ route('profile.edit') }}">@if(auth()->user()->photo_path)<img src="{{ route('profile.photo') }}" alt="Your profile photo" style="width:34px;height:34px;object-fit:cover;border-radius:50%">@else{{ strtoupper(substr(auth()->user()->name,0,1)) }}@endif</a><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }} account</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="signout" title="Sign out">↪</button></form></div></aside>
@endauth
<div class="{{ auth()->check()?'main':'public-main' }}"><header class="topbar"><span>Workspace <span class="muted"> / </span> @yield('title','Overview')</span><div class="top-actions">@auth<a class="link" href="{{ route('profile.edit') }}">My profile</a>@endauth<span class="live-dot"></span> Operations portal <span class="date">{{ now()->timezone('Asia/Kolkata')->format('d M Y') }}</span></div></header><main>
@if(session('success'))<div class="notice">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="errors" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer>{{ $branding?->name ?? 'PROPELLER LOGISTICS' }} <span>Every shipment. One clear view.</span></footer></main></div><script src="{{ asset('workspace.js') }}?v={{ filemtime(public_path('workspace.js')) }}" defer></script></body></html>