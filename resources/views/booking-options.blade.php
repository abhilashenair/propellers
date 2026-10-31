@extends('layout')
@section('title', 'Origins, destinations & services')
@section('content')
<div class="page-head"><div><h1>Booking options</h1><p class="subtext">Add, update or disable choices for new airway bills. Saved bills retain their original text.</p></div></div>
<div style="display:flex;gap:10px;flex-wrap:wrap">@foreach(\App\Models\BookingOption::TYPES as $key=>$label)<a class="button {{ $type===$key?'':'secondary' }}" href="{{ route('booking-options.index',['type'=>$key]) }}">{{ $label }}</a>@endforeach</div>
<div class="panel"><div class="panel-head"><h2>Add {{ $type }}</h2></div><form class="panel-body search" method="post" action="{{ route('booking-options.store') }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><input name="name" value="{{ old('name') }}" required maxlength="100" aria-label="New {{ $type }}" placeholder="Enter {{ $type }} name"><button class="button">＋ Add {{ $type }}</button></form></div>
<div class="panel"><div class="panel-head"><h2>{{ \App\Models\BookingOption::TYPES[$type] }}</h2><form class="search"><input type="hidden" name="type" value="{{ $type }}"><input name="q" value="{{ request('q') }}" placeholder="Search {{ $type }}" aria-label="Search {{ $type }}"><select name="status" aria-label="Filter status">@foreach(['all'=>'All statuses','enabled'=>'Enabled','disabled'=>'Disabled'] as $value=>$label)<option value="{{ $value }}" @selected(request('status','all')===$value)>{{ $label }}</option>@endforeach</select><button class="button secondary">Search</button></form></div><div class="panel-body">
@forelse($options as $option)
<form method="post" action="{{ route('booking-options.update',$option) }}" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:15px">@csrf @method('PUT')<input name="name" value="{{ $option->name }}" required maxlength="255" aria-label="{{ $type }} name" style="flex:2;min-width:180px"><select name="enabled" aria-label="Status for {{ $option->name }}" style="flex:1;min-width:120px"><option value="1" @selected($option->enabled)>Enabled</option><option value="0" @selected(!$option->enabled)>Disabled</option></select><button class="button secondary">Save changes</button></form>
@empty<p class="empty">No options found. Add your first {{ $type }} above.</p>@endforelse
</div><div class="pagination">{{ $options->links() }}</div></div>
@endsection
