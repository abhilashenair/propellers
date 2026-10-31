<?php

namespace App\Http\Controllers;

use App\Models\AirwayBill;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AirwayBillController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']);

        return view('bills.index', ['bills' => AirwayBill::with(['customer', 'vendor', 'updates'])->when($data['q'] ?? null, fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'))->latest()->paginate(15)->withQueryString()]);
    }

    public function create(): View
    {
        return view('bills.form', ['customers' => Customer::orderBy('name')->get(), 'vendors' => Vendor::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => 'nullable|string|max:100', 'carrier_number' => 'nullable|string|max:100', 'dimensions' => 'nullable|string|max:255', 'volumetric_weight' => 'required|numeric|min:0|max:100000', 'declared_value' => 'required|numeric|min:0|max:10000000|decimal:0,2',
            'number' => 'required|string|max:60|regex:/^[A-Za-z0-9-]+$/|unique:airway_bills,number', 'customer_id' => 'required|exists:customers,id', 'vendor_id' => 'required|exists:vendors,id', 'bill_date' => 'required|date',
            'origin' => ['required', 'string', 'max:255', Rule::exists('booking_options', 'name')->where('type', 'origin')->where('enabled', true)], 'destination' => ['required', 'string', 'max:255', Rule::exists('booking_options', 'name')->where('type', 'destination')->where('enabled', true)], 'consignee_name' => 'required|string|max:255', 'consignee_phone' => 'required|string|max:40', 'consignee_address' => 'required|string|max:2000', 'contents' => 'required|string|max:2000',
            'pieces' => 'required|integer|min:1|max:100000', 'weight' => 'required|numeric|min:0.001|max:100000', 'chargeable_weight' => 'required|numeric|min:0.001|max:100000|gte:weight|gte:volumetric_weight', 'service' => ['required', 'string', 'max:100', Rule::exists('booking_options', 'name')->where('type', 'service')->where('enabled', true)], 'currency' => 'required|in:INR,USD,GBP,EUR,AED',
            'freight' => 'required|numeric|min:0|max:10000000|decimal:0,2', 'other_charges' => 'required|numeric|min:0|max:10000000|decimal:0,2', 'tax_rate' => 'required|numeric|min:0|max:100|decimal:0,2', 'notes' => 'nullable|string|max:3000']);
        $subtotal = (int) round((float) $data['freight'] * 100) + (int) round((float) $data['other_charges'] * 100);
        $data['total_minor'] = $subtotal + (int) round($subtotal * (float) $data['tax_rate'] / 100);
        $data['created_by'] = $request->user()->id;
        $bill = DB::transaction(function () use ($data) {
            $shipment = Shipment::firstOrCreate(['number' => $data['number']], ['vendor_id' => $data['vendor_id'], 'origin' => $data['origin'], 'destination' => $data['destination'], 'created_by' => $data['created_by']]);
            if ((int) $shipment->vendor_id !== (int) $data['vendor_id'] || $shipment->origin !== $data['origin'] || $shipment->destination !== $data['destination']) {
                throw ValidationException::withMessages(['number' => 'This airway number already exists with different vendor or route details.']);
            }
            $data['shipper_details'] = Customer::findOrFail($data['customer_id'])->only(['name', 'address', 'phone', 'tax_number']);
            $bill = AirwayBill::create($data);
            if (! $shipment->updates()->exists()) {
                $bill->updates()->create(['created_by' => $data['created_by'], 'status' => 'Booked', 'location' => $data['origin'], 'message' => 'Shipment booked.', 'occurred_at' => now()]);
            }

            return $bill;
        });

        return redirect()->route('bills.show', $bill)->with('success', 'Airway bill created.');
    }

    public function show(AirwayBill $bill): View
    {
        $bill->load(['customer', 'vendor', 'updates']);

        return view('bills.show', ['bill' => $bill, 'company' => CompanySetting::find(1)]);
    }
}
