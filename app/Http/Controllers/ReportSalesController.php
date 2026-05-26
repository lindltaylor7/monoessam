<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\Sale;
use App\Models\Subdealership;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportSalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Cargar unidades y cafés del usuario
        $user->load(['units.cafes']);

        // Obtener todos los cafés del usuario
        $cafes = $user->units->flatMap->cafes->unique('id')->values();
        $cafeIds = $cafes->pluck('id');

        // Subconcesionarias asociadas a la mina del usuario
        $subdealerships = $user->mine_id
            ? Subdealership::whereHas('mines', fn($q) => $q->where('mines.id', $user->mine_id))
                ->orderBy('name')
                ->get(['id', 'name', 'ruc'])
            : collect();

        // Obtener filtros de la petición
        $startDate            = $request->input('start_date', date('Y-m-d'));
        $endDate              = $request->input('end_date', date('Y-m-d'));
        $cafeFilter           = $request->input('cafe_id');
        $subdealershipFilter  = $request->input('subdealership_id');

        $subdealershipName = $subdealershipFilter
            ? $subdealerships->firstWhere('id', (int) $subdealershipFilter)?->name
            : null;

        $applySubdealershipFilter = function ($q) use ($subdealershipFilter, $subdealershipName) {
            $q->whereHas('tickets', function ($tq) use ($subdealershipFilter, $subdealershipName) {
                $tq->where(function ($inner) use ($subdealershipFilter, $subdealershipName) {
                    $inner->whereHas('dinner', fn($dq) => $dq->where('subdealership_id', $subdealershipFilter));
                    if ($subdealershipName) {
                        $inner->orWhere('subdealership_name', $subdealershipName);
                    }
                });
            });
        };

        // Construir query de ventas
        $salesQuery = Sale::query()
            ->whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($user->business_id, fn($q) => $q->where('business_id', $user->business_id))
            ->when($cafeFilter, fn($q) => $q->where('cafe_id', $cafeFilter))
            ->when($subdealershipFilter, $applySubdealershipFilter)
            ->with(['tickets.dinner', 'tickets.ticket_details', 'cafe', 'cafe.unit'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        // Paginar resultados
        $sales = $salesQuery->paginate(15)->withQueryString();

        // Calcular estadísticas
        $statsBase = Sale::query()
            ->whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($user->business_id, fn($q) => $q->where('business_id', $user->business_id))
            ->when($cafeFilter, fn($q) => $q->where('cafe_id', $cafeFilter))
            ->when($subdealershipFilter, $applySubdealershipFilter);

        $totalAmount = (clone $statsBase)->sum('total');
        $totalSales  = (clone $statsBase)->count();

        return Inertia::render('reportsales/Index', [
            'sales'           => $sales,
            'cafes'           => $cafes,
            'subdealerships'  => $subdealerships,
            'filters'         => [
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'cafe_id'          => $cafeFilter,
                'subdealership_id' => $subdealershipFilter,
            ],
            'statistics' => [
                'total_amount' => $totalAmount,
                'total_sales'  => $totalSales,
                'average_sale' => $totalSales > 0 ? $totalAmount / $totalSales : 0,
            ],
        ]);
    }

    /**
     * Export sales report to Excel
     */
    public function export(Request $request)
    {
        // TODO: Implement Excel export functionality
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sale = Sale::findOrFail($id);

        // Verificar permisos del usuario
        $user = auth()->user();
        $userCafeIds = $user->units->flatMap->cafes->pluck('id');

        if (!$userCafeIds->contains($sale->cafe_id)) {
            return redirect()->back()->with('error', 'No tienes permisos para eliminar esta venta');
        }

        // Eliminar tickets y detalles relacionados
        $sale->tickets()->delete();
        $sale->sale_details()->delete();
        $sale->delete();

        return redirect()->back()->with('success', 'Venta eliminada correctamente');
    }
}
