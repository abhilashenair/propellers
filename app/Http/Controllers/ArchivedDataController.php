<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\AirwayBill;
use Illuminate\View\View;

class ArchivedDataController extends Controller
{
    public function index(RecordListRequest $request): View
    {
        return view('archived-data', ['bills' => AirwayBill::archived()->filter($request->validated())->with(['customer', 'vendor', 'latestUpdate'])->latest()->orderByDesc('id')->paginate($request->pageSize())->withQueryString()]);
    }
}
