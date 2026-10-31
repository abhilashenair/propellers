<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->validate(['q' => 'nullable|string|max:100']);

        return view('contacts.index', ['records' => Customer::query()->when($q['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'))->latest()->paginate(15)->withQueryString(), 'kind' => 'customers']);
    }

    public function create(): View
    {
        return view('contacts.form', ['record' => new Customer, 'kind' => 'customers']);
    }

    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validated($request));

        return redirect()->route('customers.index')->with('success', 'Customer created.');
    }

    public function edit(Customer $customer): View
    {
        return view('contacts.form', ['record' => $customer, 'kind' => 'customers']);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:2000', 'tax_number' => 'nullable|string|max:100']);
    }
}
