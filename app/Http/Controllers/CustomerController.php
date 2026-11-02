<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(RecordListRequest $request): View
    {
        $filters = $request->validated();
        $query = Customer::query()->when(isset($filters['q']), fn ($query) => $query->where(fn ($match) => $match->where('name', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%')->orWhere('phone', 'like', '%'.$filters['q'].'%')->orWhere('address', 'like', '%'.$filters['q'].'%')));
        foreach (['email', 'phone'] as $field) {
            $query->when(isset($filters[$field]), fn ($query) => $query->where($field, 'like', '%'.$filters[$field].'%'));
        }
        $query->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));

        return view('contacts.index', ['records' => $query->latest()->orderByDesc('id')->paginate($request->pageSize())->withQueryString(), 'kind' => 'customers']);
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
        return $request->validate(['name' => 'required|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:2000', 'tax_number' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:20']);
    }
}
