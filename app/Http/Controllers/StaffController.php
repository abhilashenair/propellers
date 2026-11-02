<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordListRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(RecordListRequest $request): View
    {
        $filters = $request->validated();
        $query = User::where('role', 'staff')->when(isset($filters['q']), fn ($query) => $query->where(fn ($match) => $match->where('name', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%')))->when(isset($filters['email']), fn ($query) => $query->where('email', 'like', '%'.$filters['email'].'%'))->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));

        return view('staff', ['staff' => $query->latest()->orderByDesc('id')->paginate($request->pageSize())->withQueryString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $user = new User($data);
        $user->password = Hash::make($data['password']);
        $user->role = 'staff';
        $user->save();

        return back()->with('success', 'Staff account created.');
    }
}
