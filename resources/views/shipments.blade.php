@extends('layout')
@section('title', 'Airway numbers')
@section('content')
<div class="page-head">
    <div><h1>Airway numbers</h1><p class="subtext">Airway numbers from created bills. View customer details and add movement updates.</p></div>
    <a class="button" href="{{ route('bills.create') }}">＋ Create airway bill</a>
</div>
<div class="panel">
    <div class="panel-head">
        <h2>Billed consignments</h2>
        <form class="search"><input name="q" value="{{ request('q') }}" placeholder="Search airway number" aria-label="Search airway number"><button class="button secondary">Search</button></form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Airway number</th><th>Customer details</th><th>Vendor / route</th><th>Latest movement</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($shipments as $shipment)
            <tr>
                <td><a class="number" href="{{ route('bills.show', $shipment->bill) }}">{{ $shipment->number }}</a><small>{{ $shipment->bill->bill_date->format('d M Y') }}</small></td>
                <td><strong>{{ $shipment->bill->customer->name }}</strong><small>{{ $shipment->bill->customer->phone }}</small><small>{{ $shipment->bill->customer->email ?: '—' }}</small><small style="white-space:normal;max-width:280px">{{ $shipment->bill->customer->address }}</small></td>
                <td>{{ $shipment->vendor->name }}<small>{{ $shipment->origin }} → {{ $shipment->destination }}</small></td>
                <td><span class="badge">{{ $shipment->updates->first()?->status ?? 'Booked' }}</span><small>{{ $shipment->updates->first()?->location }}</small></td>
                <td><a class="link" href="{{ route('shipments.show', $shipment) }}#movement-update">Add movement update ↗</a><small><a class="link" href="{{ route('bills.show', $shipment->bill) }}">View bill</a></small></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No billed consignments found. Create an airway bill to see its number here.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $shipments->links() }}</div>
</div>
@endsection
