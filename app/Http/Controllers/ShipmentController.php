<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:60']);

        return view('shipments', ['shipments' => Shipment::whereHas('bill')->with(['vendor', 'updates', 'bill.customer'])->when($data['q'] ?? null, fn ($q, $number) => $q->where('number', 'like', '%'.$number.'%'))->latest()->paginate(15)->withQueryString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['number' => 'required|string|max:60|regex:/^[A-Za-z0-9-]+$/|unique:shipments,number', 'vendor_id' => 'required|exists:vendors,id', 'origin' => 'required|string|max:255', 'destination' => 'required|string|max:255']);
        $data['created_by'] = $request->user()->id;
        $shipment = DB::transaction(function () use ($data) {
            $shipment = Shipment::create($data);
            $shipment->updates()->create(['created_by' => $data['created_by'], 'status' => 'Booked', 'location' => $data['origin'], 'message' => 'Shipment registered.', 'occurred_at' => now()]);

            return $shipment;
        });

        return redirect()->route('shipments.show', $shipment)->with('success', 'Airway number registered.');
    }

    public function show(Shipment $shipment): View
    {
        $shipment->load(['vendor', 'updates', 'bill.customer']);

        return view('shipment', compact('shipment'));
    }
}
