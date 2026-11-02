@extends('layout')
@section('title','Airway bills')
@section('content')
<div class="page-head"><div><h1>Airway bills</h1><p class="subtext">Active consignments. Delivered bills are available in Archived data.</p></div><a class="button" href="{{ route('bills.create') }}">＋ Create airway bill</a></div>
<div class="panel"><div class="panel-head"><h2>Active consignments</h2><a class="link" href="{{ route('archived-data.index') }}">Archived data ↗</a>@include('bill-filters')</div>@include('bills.table')@include('list-pagination',['records'=>$bills])</div>
@endsection
