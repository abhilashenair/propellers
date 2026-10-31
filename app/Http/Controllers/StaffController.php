<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('staff', ['staff' => User::where('role', 'staff')->latest()->paginate(15)]);
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
