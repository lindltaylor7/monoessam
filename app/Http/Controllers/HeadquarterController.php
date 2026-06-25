<?php

namespace App\Http\Controllers;

use App\Models\Headquarter;
use Illuminate\Http\Request;

class HeadquarterController extends Controller
{
    public function update(Request $request, Headquarter $headquarter)
    {
        $headquarter->update($request->only(['name', 'latitude', 'longitude', 'address']));
        return response()->json($headquarter);
    }
}
