<?php

namespace App\Http\Controllers;

use App\Imports\DinnersImport;
use App\Models\Area;
use App\Models\Cafe;
use App\Models\Dealership;
use App\Models\Dinner;
use App\Models\Receipt_type;
use App\Models\Sale;
use App\Models\Sale_type;
use App\Models\Service;
use App\Models\Subdealership;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

use function PHPUnit\Framework\isArray;

class DinnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();

        // Cargar relaciones básicas
        $user->load(['areas.cafe.services', 'units.cafes.services']);

        // Obtener cafés de todas las unidades del usuario
        $cafes = $user->units->flatMap->cafes->unique('id')->values();
        $cafeIds = $cafes->pluck('id');

        // Obtener ventas paginadas por separado
        $todaySales = Sale::whereIn('cafe_id', $cafeIds)
            ->where('date', date('Y-m-d'))
            ->orderBy('id', 'desc')
            ->with(['tickets.dinner', 'cafe'])
            ->paginate(10);

        return Inertia::render('dinners/Index', [
            'dinners' => Dinner::with('cafe')->get(),
            'services' => Service::all(),
            'units' => $user->units()->with('cafes')->get(),
            'cafes' => $cafes,
            'todaySales' => $todaySales, // Esto incluirá los links de paginación
            'sale_types' => Sale_type::all(),
            'receipt_types' => Receipt_type::all(),
            'subdealerships' => Subdealership::all(),
            'dealerships' => Dealership::all()
        ]);
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
        $dinner = Dinner::create($request->all());

        return redirect()->back();
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
        $dinner = Dinner::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'dni' => 'required|string|max:8|unique:dinners,dni,' . $dinner->id,
            'phone' => 'nullable|string|max:15',
            'subdealership_id' => 'required|exists:subdealerships,id',
            'cafe_id' => 'required|exists:cafes,id',
        ]);

        $dinner->update($request->all());

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $dinner = Dinner::findOrFail($id);
        $dinner->delete();

        return redirect()->back();
    }

    public function search(string $word, string $id)
    {
        $dinners = Dinner::where(function ($query) use ($word) {
            $query->where('name', 'like', '%' . $word . '%')
                ->orWhere('dni', 'like', '%' . $word . '%');
        })
            ->where('cafe_id', $id)
            ->with(['cafe', 'cafe.unit', 'subdealership'])
            ->take(8)
            ->get();

        $this->printTest();

        return $dinners;
    }

    public function excel(Request $request)
    {

        if ($request->hasFile('file')) {

            $fileName = time() . '.' . $request->file->getClientOriginalExtension();
            $fileSaved = $request->file->move(public_path('files'), $fileName);

            Excel::import(new DinnersImport, $fileSaved);

            return redirect()->back()->with('success', 'Archivo subido correctamente');
        }

        return redirect()->back()->with('error', 'No se pudo subir el archivo');
    }

    public function printTest()
    {
        try {
            $nombreImpresora = "EPSON TM-T20II Receipt";

            $connector = new WindowsPrintConnector($nombreImpresora);

            $printer = new Printer($connector);

            $printer->text("Hello World\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->text("SOY DIEGO\n");
            $printer->cut();
            $printer->close();

            return response()->json(['success' => 'Ticket impreso correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al imprimir: ' . $e->getMessage()]);
        }
    }
}
