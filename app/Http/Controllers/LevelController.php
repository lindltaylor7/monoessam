<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LevelController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:levels,name',
        ]);

        $level = \App\Models\Level::create([
            'name' => $request->name,
        ]);

        return response()->json($level);
    }

    public function destroy($id)
    {
        $level = \App\Models\Level::findOrFail($id);
        $level->delete();
        return response()->json(['success' => true]);
    }
}
