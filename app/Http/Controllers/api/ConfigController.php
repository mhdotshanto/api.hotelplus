<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Config;

class ConfigController extends Controller
{
    public function index(Request $request)
    {
        $configs = Config::all()->map(function ($config) {
            $config->suggestion = $config->suggestion
                ? explode(',', $config->suggestion)
                : [];

            return $config;
        });

        return response()->json($configs);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'name' => 'required|max:40|unique:configs,name',
    //         'value' => 'required',
    //     ]);

    //     Config::create([
    //         'name' => $validated['name'],
    //         'value' => $validated['value'],
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Config created successfully',
    //     ]);
    // }

    public function update(Request $request, $config)
    {
        $item = Config::findOrFail($config);

        $validated = $request->validate([
            'name' => 'required|max:40|unique:configs,name,' . $item->id,
            'value' => 'required',
        ]);

        $item->update([
            'name' => $validated['name'],
            'value' => $validated['value'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Config updated successfully',
        ]);
    }
}
