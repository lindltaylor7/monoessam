<?php

namespace App\Http\Controllers;

use App\Exports\SalesDetailExport;
use App\Exports\SalesReportExport;
use App\Exports\ValorizacionExport;
use App\Models\Sale;
use App\Models\Subdealership;
use App\Models\Ticket_detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportSalesController extends Controller
{
    /**
     * Parse cafe filter parameters into an array of cafe IDs.
     */
    protected function parseCafeFilter(Request $request): array
    {
        $cafeIds = $request->input('cafe_ids');

        if (is_null($cafeIds) || $cafeIds === '') {
            $cafeId = $request->input('cafe_id');
            if (!is_null($cafeId) && $cafeId !== '' && $cafeId !== 'all') {
                $cafeIds = [$cafeId];
            }
        }

        if (is_string($cafeIds)) {
            if ($cafeIds === 'all' || trim($cafeIds) === '') {
                return [];
            }
            $cafeIds = explode(',', $cafeIds);
        }

        if (is_array($cafeIds)) {
            $cafeIds = array_filter(array_map('intval', $cafeIds), fn($id) => $id > 0);
            return array_values(array_unique($cafeIds));
        }

        return [];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

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
        $selectedCafeIds      = $this->parseCafeFilter($request);
        $subdealershipFilter  = $request->input('subdealership_id');

        // Resolve name directly from DB so the lookup never silently returns null
        $subdealershipName = $subdealershipFilter
            ? Subdealership::find((int) $subdealershipFilter)?->name
            : null;

        $applySubdealershipFilter = function ($q) use ($subdealershipFilter, $subdealershipName) {
            $q->whereHas('tickets', function ($tq) use ($subdealershipFilter, $subdealershipName) {
                $tq->where(function ($inner) use ($subdealershipFilter, $subdealershipName) {
                    // Primary: match the denormalized subdealership_name stored on every ticket
                    if ($subdealershipName) {
                        $inner->where('subdealership_name', $subdealershipName);
                    }
                    // Fallback: match through the dinner → subdealership relation
                    $inner->orWhereHas('dinner', fn($dq) =>
                        $dq->where('subdealership_id', (int) $subdealershipFilter)
                    );
                });
            });
        };

        // Construir query de ventas
        $salesQuery = Sale::query()
            ->whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->when(!empty($selectedCafeIds), fn($q) => $q->whereIn('cafe_id', $selectedCafeIds))
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
            ->when(!empty($selectedCafeIds), fn($q) => $q->whereIn('cafe_id', $selectedCafeIds))
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
                'cafe_id'          => count($selectedCafeIds) === 1 ? $selectedCafeIds[0] : null,
                'cafe_ids'         => $selectedCafeIds,
                'subdealership_id' => $subdealershipFilter,
            ],
            'statistics' => [
                // Se castea a float: sum() devuelve int/string según el driver (SQLite vs MySQL)
                // y el front espera siempre un número.
                'total_amount' => (float) $totalAmount,
                'total_sales'  => $totalSales,
                'average_sale' => $totalSales > 0 ? (float) $totalAmount / $totalSales : 0.0,
            ],
        ]);
    }

    /**
     * Detecta ventas duplicadas dentro del rango/filtros actuales: mismo comensal (dinner_id, o
     * DNI si es visitante sin dinner_id), misma fecha y mismo código de servicio (ticket_details.code)
     * repetido en más de una venta distinta. Es el mismo criterio que ya usa SaleController::store
     * para bloquear el registro en el POS (dinner+fecha+code), pero aplicado en bloque sobre el
     * reporte en vez de comensal por comensal al momento de vender.
     */
    public function duplicates(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load(['units.cafes']);

        $cafes   = $user->units->flatMap->cafes->unique('id')->values();
        $cafeIds = $cafes->pluck('id');

        $startDate           = $request->input('start_date', date('Y-m-d'));
        $endDate             = $request->input('end_date', date('Y-m-d'));
        $selectedCafeIds     = $this->parseCafeFilter($request);
        $subdealershipFilter = $request->input('subdealership_id');

        $subdealershipName = $subdealershipFilter
            ? Subdealership::find((int) $subdealershipFilter)?->name
            : null;

        $sales = Sale::query()
            ->whereIn('cafe_id', $cafeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->when(!empty($selectedCafeIds), fn($q) => $q->whereIn('cafe_id', $selectedCafeIds))
            ->when($subdealershipFilter, function ($q) use ($subdealershipFilter, $subdealershipName) {
                $q->whereHas('tickets', function ($tq) use ($subdealershipFilter, $subdealershipName) {
                    $tq->where(function ($inner) use ($subdealershipFilter, $subdealershipName) {
                        if ($subdealershipName) {
                            $inner->where('subdealership_name', $subdealershipName);
                        }
                        $inner->orWhereHas('dinner', fn($dq) => $dq->where('subdealership_id', (int) $subdealershipFilter));
                    });
                });
            })
            ->with(['tickets.ticket_details', 'cafe'])
            ->get();

        // Aplana a una fila por (venta, servicio consumido) para poder agrupar por comensal+fecha+código.
        $rows = collect();
        foreach ($sales as $sale) {
            foreach ($sale->tickets as $ticket) {
                $dinnerKey = $ticket->dinner_id ? 'dinner-' . $ticket->dinner_id : 'dni-' . ($ticket->dni ?: 'sin-dni-' . $ticket->dinner_name);
                foreach ($ticket->ticket_details as $detail) {
                    $rows->push([
                        'sale_id'      => $sale->id,
                        'group_key'    => $dinnerKey . '|' . $sale->date . '|' . $detail->code,
                        'dinner_name'  => $ticket->dinner_name,
                        'dni'          => $ticket->dni,
                        'date'         => $sale->date,
                        'code'         => $detail->code,
                        'service_name' => $detail->service_name,
                        'cafe_name'    => $sale->cafe?->name ?? $sale->cafe_name,
                        'total'        => $sale->total,
                        'created_at'   => optional($sale->created_at)->format('d/m/Y H:i'),
                    ]);
                }
            }
        }

        $groups = $rows->groupBy('group_key')
            ->filter(fn($g) => $g->pluck('sale_id')->unique()->count() > 1)
            ->map(function ($g) {
                $first = $g->first();
                return [
                    'dinner_name'  => $first['dinner_name'],
                    'dni'          => $first['dni'],
                    'date'         => $first['date'],
                    'service_name' => $first['service_name'],
                    'code'         => $first['code'],
                    'sales'        => $g->unique('sale_id')->map(fn($r) => [
                        'sale_id'    => $r['sale_id'],
                        'cafe_name'  => $r['cafe_name'],
                        'total'      => $r['total'],
                        'created_at' => $r['created_at'],
                    ])->values(),
                ];
            })
            ->sortByDesc(fn($g) => $g['date'])
            ->values();

        return response()->json(['duplicates' => $groups]);
    }

    /**
     * Export sales report to Excel — one sheet per subdealership
     */
    public function export(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load(['units.cafes']);

        $cafeIds         = $user->units->flatMap->cafes->unique('id')->pluck('id')->all();
        $startDate       = $request->input('start_date', date('Y-m-d'));
        $endDate         = $request->input('end_date', date('Y-m-d'));
        $selectedCafeIds = $this->parseCafeFilter($request);
        $sdId            = $request->input('subdealership_id') ? (int) $request->input('subdealership_id') : null;

        $fileName = 'reporte-ventas-' . $startDate . '-a-' . $endDate . '.xlsx';

        return Excel::download(
            new SalesReportExport($startDate, $endDate, $selectedCafeIds, $sdId, $cafeIds, $user->business_id),
            $fileName,
        );
    }

    /**
     * Export Valorización — matriz diaria por persona con tabs VLZ/SISTEMA/VISITAS/REFRIGERIOS
     */
    public function exportValorizacion(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load(['units.cafes', 'business', 'mine']);

        $cafeIds         = $user->units->flatMap->cafes->unique('id')->pluck('id')->all();
        $startDate       = $request->input('start_date', date('Y-m-d'));
        $endDate         = $request->input('end_date', date('Y-m-d'));
        $selectedCafeIds = $this->parseCafeFilter($request);
        $sdId            = $request->input('subdealership_id') ? (int) $request->input('subdealership_id') : null;

        // Resolve cafe/unit for header
        $cafeInfo = ['name' => ''];
        $unitInfo = ['name' => ''];
        if (!empty($selectedCafeIds)) {
            $selectedCafes = \App\Models\Cafe::with('unit')->whereIn('id', $selectedCafeIds)->get();
            $cafeInfo  = ['name' => $selectedCafes->pluck('name')->implode(', ')];
            $unitNames = $selectedCafes->pluck('unit.name')->filter()->unique()->implode(', ');
            $unitInfo  = ['name' => $unitNames ?: ($user->mine?->name ?? '')];
        } else {
            $firstCafe = $user->units->flatMap->cafes->first();
            $cafeInfo  = ['name' => 'TODAS LAS CAFETERÍAS'];
            $unitInfo  = ['name' => $user->units->first()?->name ?? $user->mine?->name ?? ''];
        }

        $businessInfo = [
            'name'          => $user->business?->name ?? '',
            'ruc'           => $user->business?->ruc ?? '',
            'legal_address' => $user->business?->legal_address ?? $user->business?->name ?? '',
            'logo'          => $user->business?->logo ?? null,
        ];

        $aFavorDe = $sdId
            ? (Subdealership::find($sdId)?->name ?? $user->mine?->name ?? '')
            : ($user->mine?->name ?? '');

        $fileName = 'valorizacion-' . $startDate . '-a-' . $endDate . '.xlsx';

        return Excel::download(
            new ValorizacionExport(
                $startDate, $endDate, $selectedCafeIds, $sdId,
                $cafeIds, $user->business_id,
                $businessInfo, $unitInfo, $cafeInfo, $aFavorDe,
            ),
            $fileName,
        );
    }

    /**
     * Export detail — flat list: one row per service consumed
     */
    public function exportDetail(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load(['units.cafes', 'mine']);

        $cafeIds         = $user->units->flatMap->cafes->unique('id')->pluck('id')->all();
        $startDate       = $request->input('start_date', date('Y-m-d'));
        $endDate         = $request->input('end_date', date('Y-m-d'));
        $selectedCafeIds = $this->parseCafeFilter($request);
        $sdId            = $request->input('subdealership_id') ? (int) $request->input('subdealership_id') : null;

        $cafeName = 'TODAS LAS CAFETERÍAS';
        if (!empty($selectedCafeIds)) {
            $selectedCafes = \App\Models\Cafe::whereIn('id', $selectedCafeIds)->pluck('name')->all();
            if (!empty($selectedCafes)) {
                $cafeName = implode(', ', $selectedCafes);
            }
        }

        $fileName = 'detalle-consumo-' . $startDate . '-a-' . $endDate . '.xlsx';

        return Excel::download(
            new SalesDetailExport($startDate, $endDate, $selectedCafeIds, $sdId, $cafeIds, $cafeName, $user->mine_id),
            $fileName,
        );
    }

    /**
     * Remove a single ticket detail and recalculate the sale total.
     */
    public function destroyTicketDetail(string $id)
    {
        $detail = Ticket_detail::with('ticket.sale')->findOrFail($id);
        $ticket = $detail->ticket;
        $sale   = $ticket->sale;

        if ($ticket->ticket_details()->count() <= 1) {
            return redirect()->back()->with('error', 'No se puede eliminar el único ítem de la venta.');
        }

        DB::transaction(function () use ($detail, $ticket, $sale) {
            // Se resta el total de la LÍNEA (unit_price * amount), no solo unit_price:
            // con amount > 1 la resta anterior descuadraba el total. Y se recalcula el IGV
            // desde el nuevo total en vez de dejar el del importe anterior.
            $lineTotal = (float) ($detail->total ?: $detail->unit_price * max(1, (int) $detail->amount));

            $newTotal = max(0, (float) $sale->total - $lineTotal);
            $sale->total     = $newTotal;
            $sale->total_igv = round($newTotal * 0.18, 2);
            $sale->save();

            $newTicketValue = max(0, (float) $ticket->price_value - $lineTotal);
            $ticket->price_value = $newTicketValue;
            $ticket->igv         = round($newTicketValue * 0.18, 2);
            $ticket->save();

            $detail->delete();
        });

        return redirect()->back()->with('success', 'Ítem eliminado. Total actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sale = Sale::findOrFail($id);

        // Verificar permisos del usuario
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load(['units.cafes']);
        $userCafeIds = $user->units->flatMap->cafes->pluck('id');

        if (!$userCafeIds->contains($sale->cafe_id)) {
            return redirect()->back()->with('error', 'No tienes permisos para eliminar esta venta');
        }

        DB::transaction(function () use ($sale) {
            // La FK ticket_details.ticket_id es nullOnDelete, así que un borrado masivo por
            // query builder ($sale->tickets()->delete()) deja los detalles huérfanos en vez
            // de eliminarlos. Se recorren los tickets y se borran sus detalles primero.
            $sale->load('tickets.ticket_details');
            foreach ($sale->tickets as $ticket) {
                $ticket->ticket_details()->delete();
                $ticket->delete();
            }
            $sale->sale_details()->delete();
            $sale->delete();
        });

        return redirect()->back()->with('success', 'Venta eliminada correctamente');
    }
}

