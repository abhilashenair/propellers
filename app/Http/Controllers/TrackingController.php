<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public const STATUSES = ['Booked', 'Picked up', 'In transit', 'Customs clearance', 'Out for delivery', 'Delivered', 'Exception'];

    public function index(Request $request): View
    {
        $data = $request->validate(['number' => 'nullable|string|max:60', 'vendor_id' => 'nullable|integer|exists:vendors,id']);
        $bill = isset($data['number']) ? Shipment::with(['vendor', 'updates'])->where('number', $data['number'])->when($data['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))->first() : null;

        return view('tracking', compact('bill', 'data'));
    }

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'location' => 'required|string|max:255', 'message' => 'nullable|string|max:2000', 'occurred_at' => 'required|date|before_or_equal:now']);
        $data['created_by'] = $request->user()->id;
        $shipment->updates()->create($data);

        return back()->with('success', 'Tracking update published to the customer website.');
    }
}
