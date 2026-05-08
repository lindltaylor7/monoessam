<?php

namespace App\Http\Controllers;

use App\Models\Dish_category;
use Illuminate\Http\Request;
use App\Imports\DishCategoriesImport;
use App\Imports\DishCategoryDishImport;
use Maatwebsite\Excel\Facades\Excel;

class DishCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dishCategory = Dish_category::create($request->all());
        return to_route('food');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $dish_category = Dish_category::find($id);

        $dish_category->delete();

        return route('food');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new DishCategoriesImport, $request->file('file'));

        return back()->with('success', 'Categorías importadas correctamente.');
    }

    public function importRelationships(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new DishCategoryDishImport, $request->file('file'));

        return back()->with('success', 'Relaciones plato-categoría importadas correctamente.');
    }
}
