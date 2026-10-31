<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WebsiteIntegrationController extends Controller
{
    public function index(): View
    {
        return view('website-integration');
    }

    public function embed(Request $request): Response
    {
        $data = $request->validate(['number' => 'nullable|string|max:60']);
        $bill = isset($data['number']) ? Shipment::with(['vendor', 'updates'])->where('number', $data['number'])->first() : null;

        return response()->view('tracking-embed', ['data' => $data, 'bill' => $bill, 'company' => CompanySetting::find(1)])->header('Content-Security-Policy', 'frame-ancestors *')->header('X-Content-Type-Options', 'nosniff')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }
}
