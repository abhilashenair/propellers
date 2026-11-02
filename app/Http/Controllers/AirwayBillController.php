<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\AirwayBill;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\TemplateSetting;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AirwayBillController extends Controller
{
    public function index(RecordListRequest $request): View
    {
        return view('bills.index', ['bills' => AirwayBill::active()->filter($request->validated())->with(['customer', 'vendor', 'latestUpdate'])->latest()->orderByDesc('id')->paginate($request->pageSize())->withQueryString()]);
    }

    public function create(): View
    {
        return view('bills.form', ['customers' => Customer::orderBy('name')->get(), 'vendors' => Vendor::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'carrier_number' => 'nullable|string|max:100', 'dimensions' => 'nullable|string|max:255', 'volumetric_weight' => 'required|numeric|min:0|max:100000', 'declared_value' => 'required|numeric|min:0|max:10000000|decimal:0,2',
            'number' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('airway_bills', 'number'), Rule::unique('airway_bills', 'reference')], 'customer_id' => 'required|exists:customers,id', 'vendor_id' => 'required|exists:vendors,id', 'bill_date' => 'required|date',
            'origin' => ['required', 'string', 'max:255', Rule::exists('booking_options', 'name')->where('type', 'origin')->where('enabled', true)], 'destination' => ['required', 'string', 'max:255', Rule::exists('booking_options', 'name')->where('type', 'destination')->where('enabled', true)], 'consignee_name' => 'required|string|max:255', 'consignee_phone' => 'required|string|max:40', 'consignee_postal_code' => 'required|string|max:20', 'shipper_name' => 'required|string|max:255', 'shipper_phone' => 'required|string|max:40', 'shipper_postal_code' => 'required|string|max:20', 'shipper_address' => 'required|string|max:2000', 'shipper_tax_number' => 'nullable|string|max:100', 'consignee_address' => 'required|string|max:2000', 'contents' => 'required|string|max:2000',
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
            $data['shipper_details'] = ['name' => $data['shipper_name'], 'address' => $data['shipper_address'], 'phone' => $data['shipper_phone'], 'postal_code' => $data['shipper_postal_code'], 'tax_number' => $data['shipper_tax_number'] ?? null];
            unset($data['shipper_name'],$data['shipper_address'],$data['shipper_phone'],$data['shipper_postal_code'],$data['shipper_tax_number']);
            $data['terms_and_conditions'] = TemplateSetting::find(1)?->terms_and_conditions ?? '';
            $bill = AirwayBill::create($data);
            if (! $shipment->updates()->exists()) {
                $bill->updates()->create(['created_by' => $data['created_by'], 'status' => 'Booked', 'location' => $data['origin'], 'message' => 'Shipment booked.', 'occurred_at' => now()]);
            }

            return $bill;
        });

        return redirect()->route('bills.show', $bill)->with('success', 'Airway bill created.');
    }

    public function show(RecordListRequest $request, AirwayBill $bill): View
    {
        $bill->load(['customer', 'vendor', 'latestUpdate']);
        $updates = $bill->updates()->filter($request->validated())->paginate($request->pageSize())->withQueryString();

        return view('bills.show', ['bill' => $bill, 'updates' => $updates, 'company' => CompanySetting::find(1), 'terms' => $bill->terms_and_conditions ?? TemplateSetting::find(1)?->terms_and_conditions]);
    }
}
