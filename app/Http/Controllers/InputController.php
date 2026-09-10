<?php

namespace App\Http\Controllers;

use App\Imports\InputsImport;
use App\Models\Input;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class InputController extends Controller
{
    public function index()
    {
        return Inertia::render('inputs/Index', [
            'inputs' => Input::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Input::create($data);

        return redirect()->back()->with('success', 'Insumo creado correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $input = Input::findOrFail($id);

        $input->update($this->validated($request, $input->id));

        return redirect()->back()->with('success', 'Insumo actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        Input::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Insumo eliminado.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new InputsImport(), $request->file('excel_file'));

        return redirect()->back()->with('success', 'Insumos importados correctamente.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('inputs', 'code')->ignore($ignoreId),
            ],
            'name'            => ['required', 'string', 'max:255'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'unit'            => ['nullable', 'numeric', 'min:0'],
            'cost'            => ['nullable', 'numeric', 'min:0'],
            'total'           => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
