<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\BookingOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingOptionController extends Controller
{
    public function index(RecordListRequest $request): View
    {
        $data = $request->validated();
        $type = $data['type'] ?? 'origin';
        $options = BookingOption::where('type', $type)->when(isset($data['q']), fn ($query) => $query->where('name', 'like', '%'.$data['q'].'%'))->when(($data['status'] ?? 'all') !== 'all', fn ($query) => $query->where('enabled', $data['status'] === 'enabled'))->when($data['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))->when($data['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))->orderBy('name')->orderBy('id')->paginate($request->pageSize())->withQueryString();

        return view('booking-options', compact('options', 'type'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['type' => ['required', Rule::in(array_keys(BookingOption::TYPES))]]);
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(BookingOption::TYPES))], 'name' => ['required', 'string', 'max:100', Rule::unique('booking_options', 'name')->where('type', $request->input('type'))]]);
        BookingOption::create($data + ['enabled' => true]);

        return redirect()->route('booking-options.index', ['type' => $data['type']])->with('success', 'Option added.');
    }

    public function update(Request $request, BookingOption $bookingOption): RedirectResponse
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('booking_options', 'name')->where('type', $bookingOption->type)->ignore($bookingOption)], 'enabled' => 'required|boolean']);
        $bookingOption->update($data);

        return redirect()->route('booking-options.index', ['type' => $bookingOption->type])->with('success', 'Option updated. Existing bills keep their saved details.');
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(BookingOption::TYPES))], 'q' => 'nullable|string|max:100']);
        $options = BookingOption::where('type', $data['type'])->where('enabled', true)->when(isset($data['q']), fn ($query) => $query->where('name', 'like', '%'.$data['q'].'%'))->orderBy('name')->limit(20)->get(['id', 'name']);

        return response()->json($options)->header('Cache-Control', 'no-store');
    }
}
