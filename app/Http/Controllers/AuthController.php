<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $username = mb_strtolower(trim($data['username']));
        if (! Auth::attempt(['username' => $username, 'password' => $data['password'], 'is_active' => true])) {
            return back()->withErrors(['username' => 'Username ou password inválidos.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        return redirect()->route('modules.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
