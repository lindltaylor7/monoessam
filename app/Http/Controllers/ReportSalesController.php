<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\Sale;
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

        // Obtener filtros de la petición
        $startDate = $request->input('start_date', date('Y-m-d'));
        $endDate = $request->input('end_date', date('Y-m-d'));
        $cafeFilter = $request->input('cafe_id');

        // Construir query de ventas
        $salesQuery = Sale::query()
            ->whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['tickets.dinner', 'cafe', 'cafe.unit'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        // Aplicar filtro de cafetería si existe
        if ($cafeFilter) {
            $salesQuery->where('cafe_id', $cafeFilter);
        }

        // Paginar resultados
        $sales = $salesQuery->paginate(15)->withQueryString();

        // Calcular estadísticas
        $totalAmount = Sale::query()
            ->whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($cafeFilter, fn($q) => $q->where('cafe_id', $cafeFilter))
            ->sum('total');

        $totalSales = Sale::query()
            ->whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($cafeFilter, fn($q) => $q->where('cafe_id', $cafeFilter))
            ->count();

        return Inertia::render('reportsales/Index', [
            'sales' => $sales,
            'cafes' => $cafes,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'cafe_id' => $cafeFilter,
            ],
            'statistics' => [
                'total_amount' => $totalAmount,
                'total_sales' => $totalSales,
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
