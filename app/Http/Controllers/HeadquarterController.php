<?php

namespace App\Http\Controllers;

use App\Models\Headquarter;
use Illuminate\Http\Request;

class HeadquarterController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'business_id' => 'required|exists:businesses,id',
            'address'     => 'nullable|string|max:255',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        Headquarter::create($data);

        return redirect()->back();
    }

    public function update(Request $request, Headquarter $headquarter)
    {
        $headquarter->update($request->only(['name', 'latitude', 'longitude', 'address']));
        return response()->json($headquarter);
    }
}
