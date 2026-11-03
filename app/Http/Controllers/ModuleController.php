<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $modules = collect(config('modules'))->map(function (array $module, string $key) use ($request) {
            $entry = collect($module['entry_routes'])
                ->first(fn (array $option) => $request->user()->hasPermission($option['permission']));

            return [
                'key' => $key,
                'name' => $module['name'],
                'description' => $module['description'],
                'icon' => $module['icon'],
                'entry_url' => $entry ? route($entry['route']) : null,
            ];
        })->values();

        return view('modules.index', compact('modules'));
    }
}
