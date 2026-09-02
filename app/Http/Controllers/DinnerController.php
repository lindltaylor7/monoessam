<?php

namespace App\Http\Controllers;

use App\Models\Dinner;
use App\Models\Mine;
use App\Models\Sale;
use App\Models\Subdealership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DinnerController extends Controller
{
    /**
     * Documento de identidad del comensal: DNI (8 dígitos) o Carné de Extranjería
     * (hasta 12 caracteres alfanuméricos).
     */
    private const DNI_RULE = 'required|string|regex:/^[A-Za-z0-9]{8,12}$/';

    private const DNI_MESSAGES = [
        'dni.regex' => 'El documento debe tener entre 8 y 12 caracteres alfanuméricos (DNI o Carné de Extranjería).',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $mineId = $user->mine_id;

        $query = Dinner::with(['subdealership', 'mine']);

        if ($request->filled('mine_id') && $request->get('mine_id') !== 'all') {
            $query->where('mine_id', (int) $request->get('mine_id'));
        } elseif ($mineId) {
            $query->where('mine_id', $mineId);
        }

        if ($request->filled('subdealership_id') && $request->get('subdealership_id') !== 'all') {
            $query->where('subdealership_id', (int) $request->get('subdealership_id'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $subdealershipsQuery = Subdealership::query();
        if ($mineId) {
            $subdealershipsQuery->whereHas('mines', fn($q) => $q->where('mines.id', $mineId));
        }

        $minesQuery = Mine::query();
        if ($mineId) {
            $minesQuery->where('id', $mineId);
        }

        $user->load(['units.cafes']);
        $cafes = $user->units->flatMap->cafes->unique('id')->values();

        return Inertia::render('dinners/Index', [
            'dinners'        => $query->orderBy('name')->paginate(20)->withQueryString(),
            'subdealerships' => $subdealershipsQuery->orderBy('name')->get(['id', 'name']),
            'mines'          => $minesQuery->orderBy('name')->get(['id', 'name']),
            'cafes'          => $cafes,
            'filters'        => $request->only(['search', 'subdealership_id', 'mine_id']),
        ]);
    }

    public function checkDni(Request $request)
    {
        $dni       = $request->get('dni', '');
        $excludeId = $request->get('exclude_id');

        $query = Dinner::where('dni', $dni);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return response()->json(['exists' => $query->exists()]);
    }

    /**
     * Búsqueda puntual por DNI (a diferencia de checkDni, que solo confirma existencia) — usada
     * por el flujo de venta al crédito del POS para saber si el comprador ya está registrado
     * como comensal y, de ser así, prellenar su nombre/subdealership.
     */
    public function findByDni(Request $request)
    {
        $dni = trim((string) $request->get('dni', ''));

        if (strlen($dni) < 8 || strlen($dni) > 12) {
            return response()->json(['found' => false]);
        }

        $dinner = Dinner::where('dni', $dni)->with('subdealership:id,name')->first();

        if (!$dinner) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true, 'dinner' => $dinner]);
    }

    /**
     * Registro rápido de un comensal nuevo (respuesta JSON, no redirect) — para flujos fuera de
     * Inertia como el modal de venta al crédito del POS, que consume la API vía axios.
     */
    public function quickRegister(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'dni'              => self::DNI_RULE . '|unique:dinners,dni',
            'phone'            => 'nullable|string|max:20',
            'subdealership_id' => 'nullable|exists:subdealerships,id',
        ], self::DNI_MESSAGES);

        $dinner = Dinner::create([
            'name'             => $data['name'],
            'dni'              => $data['dni'],
            'phone'            => $data['phone'] ?? null,
            'subdealership_id' => $data['subdealership_id'] ?? null,
            'mine_id'          => Auth::user()->mine_id,
        ]);

        return response()->json(['dinner' => $dinner->load('subdealership:id,name')], 201);
    }

    public function save(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'dni'              => self::DNI_RULE . '|unique:dinners,dni',
            'phone'            => 'nullable|string|max:20',
            'subdealership_id' => 'nullable|exists:subdealerships,id',
        ], self::DNI_MESSAGES);

        Dinner::create([
            'name'             => $request->name,
            'dni'              => $request->dni,
            'phone'            => $request->phone,
            'subdealership_id' => $request->subdealership_id ?: null,
            'mine_id'          => Auth::user()->mine_id,
        ]);

        return redirect()->back()->with('success', 'Comensal registrado correctamente.');
    }





    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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

    public function update(Request $request, Dinner $dinner)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'dni'              => self::DNI_RULE . '|unique:dinners,dni,' . $dinner->id,
            'phone'            => 'nullable|string|max:20',
            'subdealership_id' => 'nullable|exists:subdealerships,id',
        ], self::DNI_MESSAGES);

        $dinner->update([
            'name'             => $request->name,
            'dni'              => $request->dni,
            'phone'            => $request->phone,
            'subdealership_id' => $request->subdealership_id ?: null,
            'mine_id'          => Auth::user()->mine_id,
        ]);

        return redirect()->back()->with('success', 'Comensal actualizado correctamente.');
    }

    public function destroy(Dinner $dinner)
    {
        $dinner->delete();

        return redirect()->back()->with('success', 'Comensal eliminado correctamente.');
    }


    public function report($dateInitial, $dateFinal)
    {
        $sales = Sale::with(['cafe', 'dinner', 'dinner.subdealership', 'tickets', 'tickets.ticket_details'])
            ->whereBetween('date', [$dateInitial, $dateFinal])
            ->get();
        return response()->json($sales);
    }

    public function pagination(Request $request, $offset)
    {
        $sales = Sale::with(['tickets', 'tickets.ticket_details', 'tickets.dinner', 'sale_details'])
            ->where('cafe_id', $request->cafe_id)
            ->where('date', date('Y-m-d'))
            ->orderBy('id', 'desc')
            ->offset($offset)
            ->limit(10)
            ->get();

        return response()->json($sales);
    }
}
