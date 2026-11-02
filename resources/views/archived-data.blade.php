@extends('layout')
@section('title','Archived data')
@section('content')
<div class="page-head"><div><h1>Archived data</h1><p class="subtext">Delivered consignments, with their bills and movement history preserved.</p></div><a class="button secondary" href="{{ route('bills.index') }}">Active consignments</a></div>
<div class="panel"><div class="panel-head"><h2>Delivered consignments</h2>@include('bill-filters',['archived'=>true])</div>@include('bills.table',['archived'=>true])@include('list-pagination',['records'=>$bills])</div>
@endsection
