<?php

namespace App\Http\Controllers;

use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\TrackingUpdate;
use App\Models\Vendor;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', ['bills' => AirwayBill::with(['customer', 'vendor', 'updates'])->latest()->limit(8)->get(), 'counts' => ['Consignments' => AirwayBill::count(), 'Customers' => Customer::count(), 'Logistics partners' => Vendor::count(), 'Tracking events' => TrackingUpdate::count()]]);
    }
}
