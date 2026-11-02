<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(RecordListRequest $request): View
    {
        $filters = $request->validated();
        $query = Vendor::query()->when(isset($filters['q']), fn ($query) => $query->where(fn ($match) => $match->where('name', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%')->orWhere('phone', 'like', '%'.$filters['q'].'%')->orWhere('address', 'like', '%'.$filters['q'].'%')));
        foreach (['email', 'phone'] as $field) {
            $query->when(isset($filters[$field]), fn ($query) => $query->where($field, 'like', '%'.$filters[$field].'%'));
        }
        $query->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));

        return view('contacts.index', ['records' => $query->latest()->orderByDesc('id')->paginate($request->pageSize())->withQueryString(), 'kind' => 'vendors']);
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
