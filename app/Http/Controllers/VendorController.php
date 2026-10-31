<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->validate(['q' => 'nullable|string|max:100']);

        return view('contacts.index', ['records' => Vendor::query()->when($q['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'))->latest()->paginate(15)->withQueryString(), 'kind' => 'vendors']);
    }

    public function create(): View
    {
        return view('contacts.form', ['record' => new Vendor, 'kind' => 'vendors']);
    }

    public function store(Request $request): RedirectResponse
    {
        Vendor::create($this->validated($request));

        return redirect()->route('vendors.index')->with('success', 'Vendor created.');
    }

    public function edit(Vendor $vendor): View
    {
        return view('contacts.form', ['record' => $vendor, 'kind' => 'vendors']);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($this->validated($request));

        return redirect()->route('vendors.index')->with('success', 'Vendor updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:2000', 'contact_person' => 'nullable|string|max:100']);
    }
}
