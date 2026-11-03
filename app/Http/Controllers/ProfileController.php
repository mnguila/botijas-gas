<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['salesLocation', 'groups.permissions', 'directPermissions']);
        $permissions = $user->is_super_admin
            ? Permission::orderBy('label')->get()
            : $user->directPermissions
                ->concat($user->groups->flatMap(fn ($group) => $group->permissions))
                ->unique('id')->sortBy('label')->values();

        return view('profile.show', compact('user', 'permissions'));
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'different:current_password', 'confirmed'],
        ], [
            'current_password.current_password' => 'A password atual está incorreta.',
            'password.min' => 'A nova password deve ter pelo menos 10 caracteres.',
            'password.different' => 'A nova password deve ser diferente da atual.',
            'password.confirmed' => 'A confirmação da nova password não coincide.',
        ]);

        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return redirect()->route('profile.show')->with('success', 'Password alterada com sucesso.');
    }
}
