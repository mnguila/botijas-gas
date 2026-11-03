<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesPointAssignmentController extends Controller
{
    public function index()
    {
        return view('sales.points', [
            'users' => User::with('salesLocation')->where('is_super_admin', false)
                ->where(function ($query) {
                    $query->whereNotNull('sales_location_id')
                        ->orWhereHas('directPermissions', fn ($permissions) => $permissions->where('key', 'sales.create'))
                        ->orWhereHas('groups.permissions', fn ($permissions) => $permissions->where('key', 'sales.create'));
                })->orderBy('name')->get(),
            'salesPoints' => Location::where('is_sales_point', true)->where('kind', 'physical')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is_super_admin, 403);
        $data = $request->validate([
            'sales_location_id' => ['nullable', Rule::exists('locations', 'id')->where('is_sales_point', true)->where('kind', 'physical')->where('is_active', true)],
        ]);
        abort_if(! empty($data['sales_location_id']) && ! $user->hasPermission('sales.create'), 403);

        $user->update(['sales_location_id' => $data['sales_location_id'] ?? null]);

        return redirect()->route('sales.points.index')->with('success', 'Posto atribuído atualizado.');
    }
}
