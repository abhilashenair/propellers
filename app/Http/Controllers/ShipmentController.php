<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(RecordListRequest $request): View
    {
        $filters = $request->validated();

        return view('shipments', ['shipments' => Shipment::whereHas('bill', fn ($query) => $query->active()->filter($filters))->with(['vendor', 'latestUpdate', 'bill.customer'])->latest()->orderByDesc('id')->paginate($request->pageSize())->withQueryString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['number' => 'required|string|max:60|regex:/^[A-Za-z0-9-]+$/|unique:shipments,number|unique:airway_bills,reference', 'vendor_id' => 'required|exists:vendors,id', 'origin' => 'required|string|max:255', 'destination' => 'required|string|max:255']);
        $data['created_by'] = $request->user()->id;
        $shipment = DB::transaction(function () use ($data) {
            $shipment = Shipment::create($data);
            $shipment->updates()->create(['created_by' => $data['created_by'], 'status' => 'Booked', 'location' => $data['origin'], 'message' => 'Shipment registered.', 'occurred_at' => now()]);

            return $shipment;
        });

        return redirect()->route('shipments.show', $shipment)->with('success', 'Airway number registered.');
    }

    public function show(RecordListRequest $request, Shipment $shipment): View
    {
        $shipment->load(['vendor', 'latestUpdate', 'bill.customer']);
        $updates = $shipment->updates()->filter($request->validated())->paginate($request->pageSize())->withQueryString();

        return view('shipment', compact('shipment', 'updates'));
    }
}
