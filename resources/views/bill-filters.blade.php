@php
$fields=['q'=>'Search all details','number'=>'Airway / reference number','customer'=>'Customer name','vendor'=>'Vendor name','origin'=>'Origin','destination'=>'Destination','service'=>'Service','date_from'=>'Bill date from','date_to'=>'Bill date to'];
if($archived??false) { $fields+=['delivered_from'=>'Delivered from','delivered_to'=>'Delivered to']; }
$statuses=($archived??false)?[]:array_combine(array_merge(['all'],array_values(array_diff(\App\Http\Controllers\TrackingController::STATUSES,['Delivered']))),array_merge(['All active statuses'],array_values(array_diff(\App\Http\Controllers\TrackingController::STATUSES,['Delivered']))));
@endphp
@include('record-filters',['filterFields'=>$fields,'statusOptions'=>$statuses])
