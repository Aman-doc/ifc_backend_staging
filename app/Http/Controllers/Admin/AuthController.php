<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

   public function login(Request $request)
    {
        // 1. Validation code (Aapka pehle se hoga)
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Attempt Login
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // SUCCESS: Ab ise admin dashboard ke route par redirect karein
            return redirect()->intended(route('admin.dashboard'));
        }

        // FAILED: Agar login galat ho
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }


}