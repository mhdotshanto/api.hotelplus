<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Config;

class ConfigController extends Controller
{
    public function index()
    {
        return response()->json(Config::all());
    }

    // public function store(Request $request)
    // {
        //$item = Config::findOrFail($config);
        //
        //$validated = $request->validate([
        //'name' => 'required|max:40|unique:configs,name,' . $item->id,
        //'value' => 'required',
        //]);
        //
        //$item->update([
        //'name' => $validated['name'],
        //'value' => $validated['value'],
        //]);
        //
        //return response()->json([
        //'success' => true,
        //'message' => 'Config updated successfully',
        //]);
    // }

    public function update(Request $request, Config $config)
    {
        $validated = $request->validate([
            'name' => 'required|max:40|unique:configs,name,' . $config->id,
            'value' => 'required',
        ]);
        $config->update([
            'name' => $validated['name'],
            'value' => $validated['value'],
        ]);
        return response()->json(['success' => true, 'message' => 'Config updated successfully',]);
    }
}

