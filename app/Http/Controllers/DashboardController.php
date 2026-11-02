<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\TrackingUpdate;
use App\Models\Vendor;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(RecordListRequest $request): View
    {
        return view('dashboard', ['bills' => AirwayBill::active()->filter($request->validated())->with(['customer', 'vendor', 'latestUpdate'])->latest()->orderByDesc('id')->paginate($request->pageSize())->withQueryString(), 'counts' => ['Consignments' => AirwayBill::count(), 'Customers' => Customer::count(), 'Logistics partners' => Vendor::count(), 'Tracking events' => TrackingUpdate::count()]]);
    }
}
